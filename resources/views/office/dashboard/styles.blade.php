<style>
.office-dashboard{--od-ink:#10183e;--od-muted:#5c6d91;--od-line:#e2e9f5;--od-blue:#075bec;color:var(--od-ink);background:linear-gradient(135deg,#f8fbff,#f2f6fc);padding:28px clamp(16px,2.5vw,36px) 36px;min-height:calc(100vh - 64px);font-size:14px;line-height:1.5}
.office-dashboard *{min-width:0}
.office-dashboard h1,.office-dashboard h2,.office-dashboard h3,.office-dashboard p{margin:0}
.office-dashboard a{color:inherit;text-decoration:none}
.office-dashboard a:focus-visible{outline:3px solid #075bec;outline-offset:4px;border-radius:7px}
.office-dashboard .od-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:26px}
.office-dashboard h1{font-size:clamp(25px,2.5vw,34px);line-height:1.2;font-weight:750;letter-spacing:-1px}
.office-dashboard .od-intro{font-size:16px;color:var(--od-muted);margin-top:9px}
.office-dashboard .od-today{color:var(--od-muted);white-space:nowrap;padding-top:5px;font-size:13px}
.office-dashboard .od-attention{border:1px solid #f9dce6;background:linear-gradient(135deg,#fff6f9,#fdfbff);border-radius:13px;padding:18px;margin-bottom:20px;scroll-margin-top:80px}
.office-dashboard .od-section-heading{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.office-dashboard .od-section-heading h2{font-size:20px;line-height:1.3;font-weight:750;letter-spacing:-.45px}
.office-dashboard .od-section-heading p{color:var(--od-muted);font-size:13px;margin-top:2px}
.office-dashboard .od-section-heading>.od-icon{color:#df2456}
.office-dashboard .od-attention-total{margin-left:auto;font-size:12px;color:#993453;font-weight:650;text-align:right}
.office-dashboard .od-attention-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.office-dashboard .od-attention-card{background:#fff;border:1px solid var(--od-line);border-radius:11px;padding:17px;display:flex;flex-direction:column;overflow-wrap:anywhere}
.office-dashboard .od-attention-card.od-rose{border-color:#f4afc3;background:linear-gradient(140deg,#fff8fa,#fff)}
.office-dashboard .od-card-title{display:flex;align-items:center;gap:10px;min-height:48px}
.office-dashboard .od-card-title h3{font-size:16px;font-weight:750;flex:1;letter-spacing:-.3px}
.office-dashboard .od-icon{width:25px;height:25px;flex-shrink:0;stroke:currentColor}
.office-dashboard .od-icon-wrap{display:grid;place-items:center;width:45px;height:45px;flex-shrink:0;border-radius:50%;background:#eaf2ff;color:#0962eb}
.office-dashboard .od-rose .od-icon-wrap{background:#ffe4ed;color:#df2456}
.office-dashboard .od-orange .od-icon-wrap{background:#fff0e3;color:#ce4b0b}
.office-dashboard .od-count{display:grid;place-items:center;min-width:30px;min-height:30px;border-radius:50%;background:#0962eb;color:white;font-weight:750;padding:2px 6px;font-size:15px}
.office-dashboard .od-rose .od-count{background:#df2456}
.office-dashboard .od-orange .od-count{background:#d54e0c}
.office-dashboard .od-arrow{font-size:20px;line-height:1}
.office-dashboard .od-card-description{color:var(--od-muted);font-size:13px;margin:12px 0 10px;min-height:39px}
.office-dashboard .od-attention-items{list-style:none;padding:0;margin:0}
.office-dashboard .od-attention-items li{border-top:1px solid var(--od-line)}
.office-dashboard .od-attention-item{display:block;padding:12px 0;min-height:64px}
.office-dashboard .od-attention-item strong{display:block;font-size:12px;line-height:1.5;font-weight:650}
.office-dashboard .od-item-meta{display:flex;flex-wrap:wrap;justify-content:space-between;gap:3px 8px;font-size:11px;color:var(--od-muted);margin-top:3px}
.office-dashboard .od-date-change{font-size:11px;color:#8a3551;display:block;margin-top:4px}
.office-dashboard .od-empty{color:var(--od-muted);padding:16px 0;font-size:13px;border-top:1px solid var(--od-line)}
.office-dashboard .od-card-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;font-weight:700;color:var(--od-blue);min-height:44px;margin-top:auto;padding-top:8px}
.office-dashboard .od-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
.office-dashboard .od-summary{border:1px solid var(--od-line);background:#fff;border-radius:11px;padding:18px;display:flex;flex-direction:column;gap:8px;overflow-wrap:anywhere}
.office-dashboard .od-summary-top{display:flex;align-items:center;justify-content:space-between;color:#1263b0}
.office-dashboard .od-summary h2{font-size:13px;font-weight:700;margin-top:1px}
.office-dashboard .od-value{font-size:29px;line-height:1.15;font-weight:750;letter-spacing:-.7px}
.office-dashboard .od-value.od-import-value{font-size:20px;line-height:1.35}
.office-dashboard a.od-import-value{display:flex;align-items:center;min-height:44px}
.office-dashboard .od-summary p{font-size:11px;color:var(--od-muted)}
.office-dashboard .od-panels{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.office-dashboard .od-panel{background:white;border:1px solid var(--od-line);border-radius:11px;padding:18px;overflow-wrap:anywhere}
.office-dashboard .od-panel .od-section-heading>.od-icon{color:#0065a9}
.office-dashboard .od-panel .od-section-heading h2{font-size:18px}
.office-dashboard .od-panel .od-section-heading{margin-bottom:9px}
.office-dashboard .od-view-all{margin-left:auto;color:var(--od-blue);font-size:12px;font-weight:700;display:inline-flex;align-items:center;min-height:44px;gap:8px}
.office-dashboard .od-activity-list{list-style:none;padding:0;margin:0}
.office-dashboard .od-activity-list li{border-top:1px solid var(--od-line)}
.office-dashboard .od-activity-link{display:flex;align-items:center;gap:12px;padding:12px 0;min-height:66px}
.office-dashboard .od-activity-copy{flex:1}
.office-dashboard .od-activity-copy strong{font-size:12px;font-weight:650;display:block}
.office-dashboard .od-activity-copy p{font-size:11px;color:var(--od-muted);margin-top:2px}
.office-dashboard .od-date-tile{background:#eff3f9;border-radius:8px;width:44px;min-width:44px;min-height:48px;text-align:center;padding:5px}
.office-dashboard .od-date-tile strong{display:block;font-size:18px;line-height:1.1}
.office-dashboard .od-date-tile span{font-size:11px}
.office-dashboard .od-status{font-size:10px;white-space:nowrap;background:#e4f7ed;color:#166c40;border-radius:6px;padding:5px 7px;font-weight:650}
.office-dashboard .od-relative-time{font-size:10px;color:var(--od-muted);text-align:right;max-width:70px}
.office-dashboard .od-recent-icon{width:35px;height:35px}
.office-dashboard .od-recent-icon .od-icon{width:21px;height:21px}
.office-dashboard .od-green{background:#e3f8eb;color:#008652}
.office-dashboard .od-recent-rose{background:#ffe6ed;color:#d52859}
.office-dashboard .od-quick{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin-top:20px}
.office-dashboard .od-quick h2{font-size:13px;font-weight:700;margin-right:4px}
.office-dashboard .od-quick a{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d9e3f2;background:#fff;border-radius:8px;min-height:44px;padding:9px 13px;font-weight:650;font-size:12px;color:#294b80}
.office-dashboard a:hover{color:#075bec}
.office-dashboard .od-quick a:hover{background:#edf4ff}
@media(max-width:1050px){.office-dashboard .od-attention-card{padding:13px}.office-dashboard .od-icon-wrap{width:37px;height:37px}.office-dashboard .od-card-title{gap:7px}.office-dashboard .od-card-title h3{font-size:14px}.office-dashboard .od-panel{padding:14px}.office-dashboard .od-status{white-space:normal}.office-dashboard .od-today{font-size:12px}}
@media(max-width:700px){.office-dashboard{padding:22px 14px}.office-dashboard .od-heading{flex-direction:column;gap:9px;margin-bottom:20px}.office-dashboard .od-today{padding:0}.office-dashboard .od-attention{padding:12px}.office-dashboard .od-attention-grid,.office-dashboard .od-panels{grid-template-columns:1fr}.office-dashboard .od-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.office-dashboard .od-attention-card{padding:15px}.office-dashboard .od-card-title h3{font-size:16px}.office-dashboard .od-card-description{min-height:0;margin-top:8px}.office-dashboard .od-summary{padding:14px}.office-dashboard .od-section-heading h2{font-size:18px}.office-dashboard .od-attention-total{max-width:72px}.office-dashboard .od-quick a{flex:1 1 125px}.office-dashboard .od-quick h2{flex-basis:100%}.office-dashboard .od-activity-link{gap:9px}.office-dashboard .od-panel .od-section-heading h2{font-size:17px}}
</style>
