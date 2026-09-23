<style>
.customer-detail { max-width:1600px; margin:auto; padding:24px 16px; display:grid; gap:24px; min-width:0; overflow-wrap:anywhere; }
.customer-detail .customer-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.customer-detail .customer-metric { background:#fff; border:1px solid #dce4ed; border-radius:16px; padding:18px; }
.customer-detail .customer-metric dt { font-size:13px; color:#526176; }
.customer-detail .customer-metric dd { margin-top:6px; font-size:28px; font-weight:750; color:#172b46; }
.customer-detail .customer-site-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.customer-detail .customer-site-card { display:flex; flex-direction:column; gap:16px; min-width:0; padding:20px; border:1px solid #dce4ed; border-radius:16px; background:white; box-shadow:0 2px 4px #172b4605; }
.customer-detail .customer-site-card:hover { border-color:#7baacb; background:#fafdff; }
.customer-detail .customer-site-card:focus-visible { outline:3px solid #0369a1; outline-offset:3px; }
.customer-detail .customer-site-card .open-site { margin-top:auto; border-top:1px solid #edf1f6; padding-top:14px; color:#0369a1; font-weight:700; }
.customer-detail .customer-user { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 0; border-top:1px solid #e2e8f0; }
.customer-detail .customer-user > div { min-width:0; }
.customer-detail summary { cursor:pointer; min-height:44px; padding:12px 0; font-weight:700; }
@media(min-width:640px) { .customer-detail { padding:32px 24px; } }
@media(max-width:1100px) { .customer-detail .customer-site-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:639px) { .customer-detail .customer-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .customer-detail .customer-site-grid { grid-template-columns:minmax(0,1fr); } .customer-detail .customer-user { align-items:flex-start; flex-direction:column; gap:4px; } }
</style>