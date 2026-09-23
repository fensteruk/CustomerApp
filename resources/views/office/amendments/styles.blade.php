<style>
    .am-workspace{max-width:1600px;margin:auto;padding:24px;color:#16233d;overflow-wrap:anywhere}
    .am-workspace *{box-sizing:border-box;min-width:0}
    .am-workspace a,.am-workspace button,.am-workspace select,.am-workspace input{outline-offset:4px}
    .am-workspace :focus-visible{outline:3px solid #2563eb}
    .am-heading{display:flex;justify-content:space-between;gap:20px;margin-bottom:24px}
    .am-heading h1{font-size:30px;font-weight:750;letter-spacing:-.035em}
    .am-heading p,.am-heading time{color:#596a85;font-size:14px;margin-top:6px}
    .am-heading time{white-space:nowrap}
    .am-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
    .am-metric{display:flex;align-items:center;gap:16px;background:white;border:1px solid #e1e8f1;border-radius:12px;padding:20px;text-decoration:none}
    .am-metric:hover{border-color:#94a3b8}
    .am-metric-icon{display:grid;place-items:center;width:54px;height:54px;border-radius:50%;flex-shrink:0;background:#fff0f4;color:#be185d}
    .am-metric-icon svg{width:25px;height:25px;stroke:currentColor}
    .am-amber .am-metric-icon{background:#fff6e9;color:#b45309}.am-green .am-metric-icon{background:#e6f8f0;color:#047857}
    .am-metric strong{font-size:30px;line-height:1.1}.am-metric h2{font-size:14px;font-weight:650;margin-top:5px}.am-metric p{font-size:12px;color:#64748b;margin-top:4px}
    .am-filters{display:grid;grid-template-columns:1.2fr 1fr 1fr 1fr 1.3fr;gap:12px;background:#fff;border:1px solid #e1e8f1;border-radius:12px;padding:16px;margin-bottom:20px}
    .am-filters label{font-size:12px;font-weight:600;color:#52627a}
    .am-filters input,.am-filters select{display:block;width:100%;height:44px;border:1px solid #cbd5e1;border-radius:7px;margin-top:6px;color:#24344e;background-color:#fff;font-size:13px;padding-left:10px}
    .am-filter-actions{grid-column:1/-1;display:flex;gap:18px;align-items:center;font-size:13px}.am-filter-actions>a{color:#1d4ed8;padding:12px 0}
    .am-filter-help{grid-column:1/-1;font-size:12px;color:#64748b}
    .am-button,.am-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 17px;background:#0866e6;color:#fff;border-radius:7px;font-weight:600;font-size:13px;text-align:center}
    .am-button:hover{background:#1d4ed8}.am-secondary{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
    .am-columns{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:20px;align-items:start}
    .am-panel{background:#fff;border:1px solid #e1e8f1;border-radius:12px;overflow:hidden}
    .am-panel-heading{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:18px 18px 8px}.am-panel-heading h2{font-size:17px;font-weight:700}.am-panel-heading>span{font-size:12px;color:#64748b}
    .am-queue-caption{font-size:12px;color:#64748b;padding:0 18px 15px}
    .am-queue{list-style:none;padding:0 10px;margin:0}
    .am-row{display:block;border:1px solid #e5eaf2;border-radius:9px;padding:15px;margin-bottom:9px}
    .am-row:hover{background:#f8fafc;border-color:#94a3b8}.am-row.am-selected{border-color:#f48fa8;background:#fff7fa}
    .am-row-title{display:flex;align-items:center;gap:12px;font-size:14px}.am-row-title>span:last-child{margin-left:auto;font-size:22px;color:#597098}.am-row-title strong{font-size:16px}
    .am-context{font-size:13px;color:#596a85;margin-top:4px}
    .am-row-change{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin:12px 0;font-size:14px}.am-row-change small{display:block;font-size:11px;color:#64748b;margin-bottom:3px}
    .am-row-meta{display:flex;justify-content:space-between;flex-wrap:wrap;gap:5px 12px;font-size:12px;color:#64748b}
    .am-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.am-tag{display:inline-block;background:#eef2ff;color:#3f4c83;font-size:11px;padding:4px 8px;border-radius:5px;font-weight:600}.am-warning{background:#fff2ce;color:#854d0e}
    .am-empty{padding:40px 18px;text-align:center}.am-empty>span{font-size:28px;color:#047857}.am-empty h3{font-weight:700;margin:10px 0}.am-empty p{color:#64748b;font-size:14px}.am-empty a{display:inline-block;padding:14px;color:#1d4ed8}
    .am-detail{padding:20px;scroll-margin-top:20px}.am-detail-heading{display:flex;gap:10px;align-items:center;justify-content:space-between}.am-detail h2{font-size:23px;font-weight:700;letter-spacing:-.02em}
    .am-change-banner{padding:14px;background:#f8faff;border:1px solid #e2e8f0;border-radius:8px;margin:18px 0 12px;font-size:14px}.am-change-banner p{font-size:12px;color:#64748b;margin-top:4px}
    .am-comparison{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px}.am-comparison>div{padding:13px;background:#f1f5f9;border-radius:8px}.am-comparison>div:last-child{background:#e9faf2}.am-comparison span{font-size:12px;color:#52627a}.am-comparison strong{display:block;font-size:17px;margin-top:4px}
    .am-facts{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0}.am-facts dt{color:#64748b;font-size:12px}.am-facts dd{font-size:13px;font-weight:600;margin-top:3px}.am-facts dd span{display:block;font-weight:400;color:#64748b;font-size:12px}
    .am-explanation{font-size:13px;white-space:pre-line;margin-bottom:15px}
    .am-notice{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:12px;margin:12px 0}
    .am-timeline-heading{font-size:17px;font-weight:700;margin-top:22px}.am-muted{font-size:12px;color:#64748b;margin:5px 0 16px}
    .am-timeline{list-style:none;margin:18px 0 10px 7px;padding:0;border-left:2px solid #dce5f3}.am-timeline li{position:relative;padding:0 0 22px 20px;font-size:13px}.am-timeline li:before{content:"";position:absolute;left:-7px;top:3px;width:12px;height:12px;border:2px solid #8aaddd;background:white;border-radius:50%}.am-timeline time{display:block;color:#64748b;font-size:11px;margin:3px 0}.am-timeline p{margin-top:4px;color:#52627a}
    .am-manual{background:#f7f9fc;border:1px solid #e2e8f0;border-radius:8px;padding:13px;margin-top:18px}.am-manual h3{font-size:13px;font-weight:700}.am-manual p{font-size:12px;color:#52627a;margin-top:5px}
    .am-detail-actions{display:flex;gap:10px;margin-top:14px}.am-detail-actions>*{flex:1}
    .am-pagination{padding:12px}.am-pagination:empty{display:none}
    @media(max-width:1100px){.am-columns{grid-template-columns:1fr}.am-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.am-search{grid-column:1/-1}.am-heading{flex-wrap:wrap}.am-metric{padding:15px;gap:10px}.am-metric-icon{width:38px;height:38px}.am-detail{max-width:none}}
    @media(max-width:600px){.am-metrics{grid-template-columns:1fr;gap:8px}.am-metric{padding:13px 16px}.am-metric>div{display:grid;grid-template-columns:42px 1fr;gap:2px 12px;flex:1}.am-metric strong{grid-row:1/3;font-size:27px}.am-metric h2,.am-metric p{margin:0}.am-heading h1{font-size:27px}.am-heading time{display:none}.am-filters{padding:12px;gap:10px}.am-filters label:first-child,.am-search{grid-column:1/-1}.am-row-title{flex-wrap:wrap;gap:8px}.am-detail{padding:15px}.am-detail-heading{align-items:flex-start;flex-direction:column}.am-facts{gap:12px}.am-comparison{gap:6px}.am-comparison>div{padding:10px}.am-comparison strong{font-size:15px}.am-detail-actions{flex-direction:column}}
    .am-detail,#am-queue-title{scroll-margin-top:88px}
    .am-back{display:inline-block;padding:10px 0;color:#1d4ed8;font-size:13px;min-height:44px}
    @media(max-width:600px){.am-workspace{padding:16px}}
    @media(max-width:360px){.am-filters{grid-template-columns:minmax(0,1fr)}}
</style>
