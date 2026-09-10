"""Trace site-logo.png into an animated-ready SVG with named groups."""
from __future__ import annotations

import shutil
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

SRC = Path("public_html/logo/newlogo.png")
PNG_DST = Path("public_html/logo/site-logo.png")
SVG_DST = Path("public_html/logo/site-logo.svg")


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
    parts = [f"M{pts[0, 0]:.1f} {pts[0, 1]:.1f}"]
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

    for i, cnt in enumerate(contours):
        if hierarchy[i][3] != -1:
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
            child = hierarchy[child][0]
        xs = cnt[:, 0, 0]
        ys = cnt[:, 0, 1]
        shapes.append({
            "d": d + ((" " + " ".join(holes)) if holes else ""),
            "area": area,
            "cx": float(np.mean(xs)),
            "cy": float(np.mean(ys)),
        })
    shapes.sort(key=lambda s: s["area"], reverse=True)
    return shapes


def color_mask(rgb: np.ndarray, alpha: np.ndarray, kind: str) -> np.ndarray:
    r = rgb[:, :, 0].astype(np.int16)
    g = rgb[:, :, 1].astype(np.int16)
    b = rgb[:, :, 2].astype(np.int16)
    opaque = alpha > 72
    if kind == "red":
        m = opaque & (r > g + 24) & (r > b + 24) & (r > 120)
    elif kind == "green":
        m = opaque & (g > r + 12) & (g > b + 8) & (g > 70)
    else:
        m = opaque & ~((r > g + 24) & (r > b + 24) & (r > 120))
        m &= ~((g > r + 12) & (g > b + 8) & (g > 70))
        m &= np.maximum(np.maximum(r, g), b) > 24
    mask = m.astype(np.uint8) * 255
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel, iterations=1)
    mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel, iterations=1)
    return mask


def path_el(shape: dict, fill: str, cls: str) -> str:
    return (
        f'    <path class="logo-piece {cls}" fill="{fill}" stroke="{fill}" '
        f'stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" '
        f'paint-order="stroke fill" d="{shape["d"]}"/>'
    )


def classify_red(shape: dict, mid_x: float) -> str | None:
    if shape["cy"] < 520 and shape["area"] > 2500:
        return "logo-tulip-right" if shape["cx"] >= mid_x else "logo-tulip-left"
    if 500 < shape["cy"] < 900 and shape["area"] < 12000:
        return "logo-ribbon-red"
    return None


def classify_green(shape: dict) -> str | None:
    if shape["cy"] < 210:
        return "logo-branch"
    if shape["cy"] >= 420:
        return "logo-ribbon-green"
    return None


def classify_gray(shape: dict, mid_x: float) -> str | None:
    if shape["cy"] < 210 and shape["area"] > 400:
        return "logo-bird"
    if shape["cy"] > 760:
        return "logo-script-right" if shape["cx"] >= mid_x else "logo-script-left"
    if shape["area"] > 180:
        return "logo-frame"
    return None


def main() -> None:
    if not SRC.is_file():
        raise SystemExit(f"missing source logo: {SRC}")

    shutil.copy2(SRC, PNG_DST)

    img = Image.open(SRC).convert("RGBA")
    arr = np.array(img)
    rgb, alpha = arr[:, :, :3], arr[:, :, 3]
    h, w = arr.shape[:2]
    mid_x = w / 2

    buckets: dict[str, list[tuple[dict, str, str]]] = {
        "logo-frame": [],
        "logo-tulip": [],
        "logo-ribbon": [],
        "logo-bird": [],
        "logo-branch": [],
        "logo-script": [],
    }

    for shape in extract_shapes(color_mask(rgb, alpha, "red"), min_area=120):
        cls = classify_red(shape, mid_x)
        if cls:
            buckets["logo-tulip" if cls.startswith("logo-tulip") else "logo-ribbon"].append(
                (shape, "#c41e3a", cls)
            )

    for shape in extract_shapes(color_mask(rgb, alpha, "green"), min_area=16):
        cls = classify_green(shape)
        if cls:
            group = "logo-branch" if cls == "logo-branch" else "logo-ribbon"
            buckets[group].append((shape, "#15803d", cls))

    for shape in extract_shapes(color_mask(rgb, alpha, "gray"), min_area=14):
        cls = classify_gray(shape, mid_x)
        if cls:
            if cls == "logo-bird":
                group = "logo-bird"
            elif cls == "logo-frame":
                group = "logo-frame"
            else:
                group = "logo-script"
            buckets[group].append((shape, "#374151", cls))

    chunks = ['<g id="logo-mark">']
    counts: dict[str, int] = {}
    for gid, items in buckets.items():
        if not items:
            continue
        chunks.append(f'  <g id="{gid}">')
        for shape, color, cls in items:
            chunks.append(path_el(shape, color, cls))
            key = f"{gid}:{cls}"
            counts[key] = counts.get(key, 0) + 1
        chunks.append("  </g>")
    chunks.append("</g>")

    svg = [
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" fill="none" aria-hidden="true">',
        "  <title>موسسه ایثار</title>",
        *chunks,
        "</svg>",
        "",
    ]
    SVG_DST.write_text("\n".join(svg), encoding="utf-8")
    print("wrote", PNG_DST)
    print("wrote", SVG_DST, "bytes", SVG_DST.stat().st_size)
    print("counts", counts)


if __name__ == "__main__":
    main()
