@once
@push('styles')
<style>
.bnb-fin-modal { margin: -.25rem 0; }
.bnb-fin { display: flex; flex-direction: column; gap: 1rem; font-size: .88rem; color: #222; }
.bnb-fin-currency { font-size: .78em; font-weight: 500; color: #717171; }
.bnb-fin-hero { background: linear-gradient(135deg, #f0f7ff 0%, #fff 100%); border: 1px solid #cfe2ff; border-radius: 12px; padding: 1rem 1.1rem; text-align: center; }
.bnb-fin-hero__label { font-size: .8rem; color: #717171; margin-bottom: .35rem; }
.bnb-fin-hero__amount { font-size: 1.65rem; font-weight: 700; color: #0a66c2; line-height: 1.2; }
.bnb-fin-hero__note { margin-top: .75rem; display: flex; flex-direction: column; align-items: center; gap: .35rem; }
.bnb-fin-hero__sub { font-size: .75rem; color: #717171; }
.bnb-fin-pill { display: inline-block; padding: .2rem .65rem; border-radius: 999px; font-size: .74rem; font-weight: 600; }
.bnb-fin-pill--warn { background: #fff8e6; color: #9a6700; border: 1px solid #fde68a; }
.bnb-fin-section { border: 1px solid #ddd; border-radius: 8px; background: #fff; overflow: hidden; }
.bnb-fin-section--summary { border-color: #cfe2ff; background: #fafcff; }
.bnb-fin-section__head { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid #ddd; background: #f7f7f7; }
.bnb-fin-section__icon { width: 2.1rem; height: 2.1rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
.bnb-fin-section__icon--stay { background: #e8f1ff; color: #0a66c2; }
.bnb-fin-section__icon--svc { background: #eefbf3; color: #198754; }
.bnb-fin-section__icon--sum { background: #f3f0ff; color: #6f42c1; }
.bnb-fin-section__title { margin: 0; font-size: .92rem; font-weight: 700; }
.bnb-fin-section__meta { margin: .15rem 0 0; font-size: .74rem; color: #717171; }
.bnb-fin-section__aside { margin-right: auto; font-weight: 700; font-size: .9rem; color: #222; }
.bnb-fin-section__body { padding: .35rem 1rem .65rem; }
.bnb-fin-section__body--stack { padding: .65rem; display: flex; flex-direction: column; gap: .65rem; }
.bnb-fin-subblock { margin: .35rem 0 .15rem; padding: .5rem .65rem; border-radius: 8px; background: #fff5f5; border: 1px solid #fecaca; }
.bnb-fin-subblock__title { font-size: .72rem; font-weight: 700; color: #b91c1c; margin-bottom: .25rem; }
.bnb-fin-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid #f0f0f0; }
.bnb-fin-row:last-child { border-bottom: none; }
.bnb-fin-row--compact { padding: .35rem 0; }
.bnb-fin-row__label { flex: 1; min-width: 0; line-height: 1.45; }
.bnb-fin-row__hint { display: block; margin-top: .1rem; font-size: .72rem; color: #717171; font-weight: 400; }
.bnb-fin-row__amount { flex-shrink: 0; text-align: left; font-weight: 600; white-space: nowrap; }
.bnb-fin-row__sign { margin-left: .15rem; }
.bnb-fin-row--muted .bnb-fin-row__amount { color: #717171; font-weight: 500; }
.bnb-fin-row--discount .bnb-fin-row__label, .bnb-fin-row--discount .bnb-fin-row__amount { color: #b91c1c; }
.bnb-fin-row--adjustment .bnb-fin-row__label, .bnb-fin-row--adjustment .bnb-fin-row__amount { color: #b45309; }
.bnb-fin-row--total .bnb-fin-row__label { font-weight: 700; }
.bnb-fin-row--total .bnb-fin-row__amount { font-weight: 700; }
.bnb-fin-row--hero .bnb-fin-row__label { font-weight: 700; font-size: .95rem; }
.bnb-fin-row--hero .bnb-fin-row__amount { font-size: 1.15rem; font-weight: 800; color: #0a66c2; }
.bnb-fin-row--final { margin-top: .25rem; padding-top: .75rem; border-top: 2px solid #cfe2ff !important; }
.bnb-fin-service { border: 1px solid #ddd; border-radius: 10px; background: #fff; overflow: hidden; }
.bnb-fin-service__head { display: flex; justify-content: space-between; gap: .75rem; padding: .65rem .75rem; background: #f7f7f7; border-bottom: 1px solid #ddd; }
.bnb-fin-service__title { margin: 0; font-size: .86rem; font-weight: 700; }
.bnb-fin-service__meta { font-size: .7rem; color: #717171; margin-top: .15rem; }
.bnb-fin-service__qty { font-size: .72rem; color: #717171; white-space: nowrap; direction: ltr; }
.bnb-fin-service__body { padding: .15rem .75rem; }
.bnb-fin-service__foot { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .55rem .75rem; border-top: 1px solid #ddd; background: #f8fbff; font-size: .82rem; }
.bnb-fin-service__foot strong { color: #0a66c2; font-size: .92rem; }
@media (max-width: 575.98px) {
  .bnb-fin-hero__amount { font-size: 1.35rem; }
  .bnb-fin-section__head { flex-wrap: wrap; }
  .bnb-fin-section__aside { width: 100%; text-align: left; margin-top: .25rem; }
  .bnb-fin-row { flex-direction: column; gap: .2rem; }
  .bnb-fin-row__amount { text-align: right; width: 100%; }
}
</style>
@endpush
@endonce
