<style>
    .recon-workspace { min-width:0; color:#17324e; }
    .recon-workspace *, .recon-workspace *::before, .recon-workspace *::after { box-sizing:border-box; }
    .recon-workspace :focus-visible { outline:3px solid #1266d6; outline-offset:3px; }
    .recon-heading { display:flex; gap:20px; justify-content:space-between; align-items:center; margin:20px 0; }
    .recon-heading > div { min-width:0; }
    .recon-heading .secondary-button { flex-shrink:0; }
    .recon-notice, .recon-warning { padding:14px 16px; border:1px solid #bed7f8; border-radius:10px; background:#f0f6ff; font-size:14px; line-height:1.6; margin:14px 0; }
    .recon-notice strong { color:#174e9a; }
    .recon-warning { background:#fffbeb; border-color:#ead28c; color:#704811; }
    .recon-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-top:24px; }
    .recon-metric { padding:20px; border:1px solid #dce6f2; border-radius:12px; background:white; }
    .recon-metric p { font-weight:700; font-size:14px; }
    .recon-metric strong { display:block; font-size:30px; line-height:1.4; color:#122744; }
    .recon-metric span, .recon-caption { font-size:13px; color:#52657d; line-height:1.6; }
    .recon-caption { margin:12px 0; }
    .recon-grid { display:grid; grid-template-columns:minmax(0,1fr) 310px; gap:18px; margin-top:22px; align-items:start; }
    .recon-panel { padding:20px; border:1px solid #dce6f2; border-radius:12px; background:white; min-width:0; overflow-wrap:anywhere; }
    .recon-filters { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:20px 0; align-items:end; }
    .recon-filters label { display:block; min-width:0; font-size:13px; font-weight:700; }
    .recon-filters .form-input { display:block; width:100%; min-width:0; max-width:100%; margin-top:6px; min-height:44px; font-size:14px; }
    .recon-table { width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed; }
    .recon-table th { text-align:left; background:#f0f5fb; color:#354d6b; font-weight:700; padding:12px 8px; }
    .recon-table td { padding:14px 8px; vertical-align:top; border-bottom:1px solid #e4ebf3; }
    .recon-table td span { display:block; margin-top:4px; }
    .recon-selected { background:#f0f6ff; }
    .recon-status { display:inline-block; padding:6px 9px; border-radius:8px; background:#fff4d8; color:#714809; font-size:12px; font-weight:700; }
    .recon-detail-link { display:inline-flex; align-items:center; min-height:44px; color:#135bbc; font-weight:700; text-decoration:underline; text-underline-offset:3px; }
    .recon-empty { padding:28px 16px !important; color:#52657d; }
    .recon-pagination { margin-top:18px; }
    .recon-comparison h3, .recon-site h3 { font-weight:750; margin-top:14px; }
    .recon-values { margin-top:18px; border:1px solid #dce6f2; border-radius:8px; overflow:hidden; }
    .recon-values > div { padding:14px; border-bottom:1px solid #dce6f2; }
    .recon-values > div:last-child { border:0; }
    .recon-values dt { font-weight:700; font-size:13px; color:#52657d; margin-bottom:6px; }
    .recon-values dd { font-size:15px; }
    .recon-values dd span, .recon-history li span { display:block; font-size:12px; color:#52657d; margin-top:5px; }
    .recon-portal-value { background:#f0f6ff; }
    .recon-request { width:100%; }
    .recon-history { margin-top:24px; border-top:1px solid #dce6f2; }
    .recon-history li { padding:12px 0; font-size:13px; border-bottom:1px solid #e4ebf3; }
    .recon-provenance { margin-top:20px; font-size:12px; }
    .recon-provenance summary { display:list-item; padding:12px 0; cursor:pointer; font-weight:700; min-height:44px; }
    .recon-provenance dt { font-weight:700; margin-top:12px; }
    .recon-provenance p { margin-top:12px; }
    .recon-sites { margin-top:22px; }
    .recon-site-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
    .recon-site { padding:16px; border:1px solid #dce6f2; border-radius:8px; min-width:0; font-size:14px; }
    .recon-site h3 { margin-top:0; }
    @media(max-width:1250px) {
        .recon-table, .recon-table tbody, .recon-table tr, .recon-table td { display:block; }
        .recon-table thead { position:absolute; width:1px; height:1px; padding:0; overflow:hidden; clip:rect(0,0,0,0); }
        .recon-table tr { border:1px solid #dce6f2; border-radius:8px; margin-top:12px; padding:10px; }
        .recon-table td { padding:7px; border:0; }
        .recon-table td[data-label]::before { content:attr(data-label); display:block; font-size:11px; font-weight:700; color:#52657d; margin-bottom:3px; }
    }
    @media(max-width:1000px) { .recon-grid { grid-template-columns:minmax(0,1fr); } .recon-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .recon-site-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .recon-heading { align-items:start; flex-direction:column; } }
    @media(max-width:540px) { .recon-filters, .recon-site-grid { grid-template-columns:minmax(0,1fr); } .recon-panel { padding:14px; } .recon-metric { padding:14px; } .recon-metrics { gap:10px; } .recon-metric p { font-size:12px; } .recon-metric span { font-size:12px; } .recon-heading .admin-title { font-size:26px; } }
</style>
