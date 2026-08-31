"""Trace site-logo.png into an animated-ready SVG with named groups."""
from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np
from PIL import Image

SRC = Path("public_html/logo/site-logo.png")
DST = Path("public_html/logo/site-logo.svg")


def approx_contour(contour: np.ndarray, epsilon_ratio: float = 0.0018) -> np.ndarray:
    peri = cv2.arcLength(contour, True)
    eps = max(0.8, peri * epsilon_ratio)
    approx = cv2.approxPolyDP(contour, eps, True)
    if len(approx) < 3:
        return contour
    return approx


def contour_d(contour: np.ndarray) -> str:
    pts = approx_contour(contour).reshape(-1, 2)
    if len(pts) < 3:
        return ""
    parts = [f"M{pts[0,0]:.1f} {pts[0,1]:.1f}"]
    for x, y in pts[1:]:
        parts.append(f"L{x:.1f} {y:.1f}")
    parts.append("Z")
    return " ".join(parts)


def extract_shapes(mask: np.ndarray, min_area: float = 40.0) -> list[dict]:
    contours, hierarchy = cv2.findContours(mask, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_NONE)
    if hierarchy is None:
        return []
    hierarchy = hierarchy[0]
    shapes: list[dict] = []
    used_children: set[int] = set()

    for i, cnt in enumerate(contours):
        parent = hierarchy[i][3]
        if parent != -1:
            continue
        area = abs(cv2.contourArea(cnt))
        if area < min_area:
            continue
        d = contour_d(cnt)
        if not d:
            continue
        child = hierarchy[i][2]
        holes = []
        while child != -1:
            hole = contours[child]
            if abs(cv2.contourArea(hole)) >= min_area * 0.4:
                hd = contour_d(hole)
                if hd:
                    holes.append(hd)
            used_children.add(child)
            child = hierarchy[child][0]
        shapes.append({
            "d": d + ((" " + " ".join(holes)) if holes else ""),
            "area": area,
            "has_holes": bool(holes),
        })
    shapes.sort(key=lambda s: s["area"], reverse=True)
    return shapes


def color_mask(rgb: np.ndarray, alpha: np.ndarray, kind: str) -> np.ndarray:
    r = rgb[:, :, 0].astype(np.int16)
    g = rgb[:, :, 1].astype(np.int16)
    b = rgb[:, :, 2].astype(np.int16)
    opaque = alpha > 72
    if kind == "red":
        m = opaque & (r > g + 28) & (r > b + 28) & (r > 140)
    elif kind == "green":
        m = opaque & (g > r + 18) & (g > b) & (g > 90)
    else:
        m = opaque & ~((r > g + 28) & (r > b + 28) & (r > 140))
        m &= ~((g > r + 18) & (g > b) & (g > 90))
        m &= (np.maximum(np.maximum(r, g), b) > 28)
    mask = m.astype(np.uint8) * 255
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel, iterations=1)
    mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel, iterations=1)
    return mask


def path_el(shape: dict, fill: str, cls: str) -> str:
    return (
        f'    <path class="{cls}" fill="{fill}" stroke="{fill}" '
        f'stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" '
        f'paint-order="stroke fill" d="{shape["d"]}"/>'
    )


def main() -> None:
    img = Image.open(SRC).convert("RGBA")
    arr = np.array(img)
    rgb, alpha = arr[:, :, :3], arr[:, :, 3]

    groups = {
        "logo-hand": ("red", "#dc2626"),
        "logo-bird": ("green", "#059669"),
        "logo-script": ("gray", "#374151"),
    }

    chunks = ['<g id="logo-mark">']
    counts = {}
    for gid, (kind, color) in groups.items():
        mask = color_mask(rgb, alpha, kind)
        shapes = extract_shapes(mask, min_area=28 if kind != "gray" else 18)
        counts[gid] = (len(shapes), float(sum(s["area"] for s in shapes)))
        if not shapes:
            continue
        if gid == "logo-bird" and len(shapes) >= 2:
            body = [s for s in shapes if s["area"] >= 1200]
            line = [s for s in shapes if s["area"] < 1200]
            chunks.append(f'  <g id="{gid}">')
            if body:
                chunks.append('    <g id="logo-bird-body">')
                chunks.extend(path_el(s, color, "logo-path logo-path-body") for s in body)
                chunks.append("    </g>")
            if line:
                chunks.append('    <g id="logo-bird-line">')
                chunks.extend(path_el(s, color, "logo-path logo-path-line") for s in line)
                chunks.append("    </g>")
            chunks.append("  </g>")
        else:
            chunks.append(f'  <g id="{gid}">')
            cls = "logo-path logo-path-hand" if gid == "logo-hand" else "logo-path logo-path-script"
            chunks.extend(path_el(s, color, cls) for s in shapes)
            chunks.append("  </g>")
    chunks.append("</g>")

    h, w = arr.shape[:2]
    svg = [
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" fill="none" aria-hidden="true">',
        "  <title>ایثار</title>",
        *chunks,
        "</svg>",
        "",
    ]
    DST.write_text("\n".join(svg), encoding="utf-8")
    print("wrote", DST, "bytes", DST.stat().st_size)
    print("counts", counts)


if __name__ == "__main__":
    main()
