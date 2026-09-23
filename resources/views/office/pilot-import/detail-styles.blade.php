<style>
    .wald-detail { color:#14233e; max-width:1800px; margin:auto; min-width:0; font-size:14px; }
    .wald-detail * { min-width:0; box-sizing:border-box; }
    .wald-detail :focus-visible { outline:3px solid #087db2; outline-offset:3px; }
    .wald-detail [id] { scroll-margin-top:90px; scroll-margin-bottom:160px; }
    .wald-detail p { line-height:1.65; margin:8px 0; }
    .wald-heading,.wald-section-heading { display:flex; align-items:start; justify-content:space-between; gap:18px; flex-wrap:wrap; margin:20px 0; }
    .wald-heading > div { flex:1 1 280px; }
    .wald-badges,.wald-links { display:flex; gap:10px; flex-wrap:wrap; margin:18px 0; }
    .wald-badges span { background:#e8edf3; padding:6px 10px; border-radius:20px; font-weight:700; font-size:11px; }
    .wald-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin:22px 0; }
    .wald-metrics article,.wald-panel { background:white; border:1px solid #dce5ee; border-radius:14px; padding:22px; }
    .wald-metrics article { display:flex; flex-direction:column; gap:6px; }
    .wald-metrics strong { font-size:28px; }
    .wald-metrics small,.wald-facts dt { color:#56677d; font-size:12px; }
    .wald-panel { margin:20px 0; overflow-wrap:anywhere; }
    .wald-notice,.wald-found,.wald-preview,.wald-confirm { border:1px solid #c7ddec; background:#f0f8fc; border-radius:10px; padding:18px; margin:18px 0; }
    .wald-warning,.wald-question { border-color:#e3c778; background:#fffbef; }
    .wald-danger { border-color:#ebbdc2; background:#fff1f2; color:#86182d; }
    .wald-notice ul { list-style:disc; padding-left:20px; }
    .wald-found h4,.wald-preview h4,.wald-confirm h4,.wald-subtitle { font-size:18px; font-weight:750; }
    .wald-status { display:inline-block; border-radius:20px; background:#e9f2fa; color:#175679; padding:7px 12px; font-size:12px; font-weight:750; }
    .wald-safety { font-weight:650; color:#735014; }
    .wald-result { border:1px solid #9ed0b6; background:#effaf4; border-radius:12px; padding:20px; margin:24px 0; }
    .wald-result h4 { font-size:23px; font-weight:800; }
    .wald-timeline { padding-left:18px; border-left:2px solid #dce5ee; margin:16px 0; }
    .wald-timeline li { padding:12px 0; }
    .wald-steps { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:8px; margin:22px 0; }
    .wald-steps li { font-size:12px; color:#53637b; border-bottom:3px solid #dce5ee; padding:8px 4px; }
    .wald-steps li > span { display:block; font-weight:800; margin-bottom:5px; }
    .wald-steps .is-current { color:#075b82; font-weight:800; border-color:#087db2; background:#ecf7fc; border-radius:6px 6px 0 0; }
    .wald-steps .is-complete { border-color:#76b89a; }
    .wald-question { border-width:1px; border-style:solid; padding:18px; border-radius:10px; margin:18px 0; }
    .wald-question legend { font-size:17px; font-weight:750; }
    .wald-choice,.wald-check { display:flex; gap:12px; align-items:start; padding:14px 0; min-height:48px; line-height:1.6; cursor:pointer; }
    .wald-choice { padding:14px; margin:10px 0; border:1px solid #d2dce7; border-radius:8px; background:white; }
    .wald-choice input,.wald-check input { width:20px; height:20px; flex-shrink:0; margin-top:2px; }
    .wald-choice small { display:block; color:#53637b; }
    .wald-detail .form-input { display:block; width:100%; max-width:100%; margin:6px 0 14px; min-height:44px; }
    .wald-detail summary { padding:13px 0; font-weight:700; cursor:pointer; min-height:46px; }
    .wald-disclosure { border-top:1px solid #dce5ee; margin-top:18px; }
    .wald-detail pre { white-space:pre-wrap; overflow-wrap:anywhere; font-size:12px; background:#f3f6fa; padding:12px; border-radius:6px; max-height:350px; overflow:auto; }
    .wald-facts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin:18px 0; }
    .wald-facts dd { font-weight:650; margin-top:5px; }
    .wald-table { width:100%; table-layout:fixed; border-collapse:collapse; font-size:13px; }
    .wald-table th,.wald-table td { padding:12px; border-bottom:1px solid #dce5ee; text-align:left; vertical-align:top; }
    .wald-table thead { background:#eff4fa; }
    .wald-table td span,.wald-table th span { display:block; margin-top:4px; font-weight:400; }
    .wald-action-bar { position:sticky; bottom:12px; z-index:5; background:#fff; border:1px solid #b7cfdf; border-radius:12px; padding:14px; margin-top:22px; box-shadow:0 6px 24px #173f6026; display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .wald-action-bar span { display:block; font-size:12px; color:#53637b; margin-top:4px; }
    .wald-action-bar .primary-button { flex-shrink:0; }
    @media(max-width:900px) { .wald-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .wald-facts { grid-template-columns:minmax(0,1fr); } }
    @media(max-width:640px) { .wald-panel { padding:16px; } .wald-steps { grid-template-columns:repeat(3,minmax(0,1fr)); } .wald-table,.wald-table tbody,.wald-table tr,.wald-table th,.wald-table td { display:block; } .wald-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); } .wald-table tr { border:1px solid #dce5ee; border-radius:8px; margin:12px 0; } .wald-table [data-label]::before { content:attr(data-label); display:block; font-size:11px; font-weight:700; color:#53637b; margin-bottom:4px; } .wald-action-bar { flex-direction:column; align-items:stretch; bottom:8px; gap:8px; padding:12px; } .wald-action-bar > div { font-size:12px; } .wald-action-bar span { display:inline; margin-left:6px; } .wald-action-bar .primary-button { width:100%; font-size:13px; } .wald-metrics article { padding:14px; } .wald-detail .admin-title { font-size:28px; } }
</style>
