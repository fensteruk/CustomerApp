<style>
.imports-workspace{
    color:#14233e;
    max-width:1800px;
    margin:auto;
    font-size:14px;
}
.imports-workspace *{
    min-width:0;
}
.imports-heading,.imports-section-heading{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    flex-wrap:wrap;
}
.imports-intro,.imports-section-heading p{
    color:#53637b;
    font-size:13px;
    margin-top:4px;
}
.imports-badges{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.imports-badges span{
    background:#e8edf3;
    padding:5px 10px;
    border-radius:20px;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
}
.imports-upload,.imports-card,.imports-history-wrap{
    background:white;
    border:1px solid #d5dfeb;
    border-radius:8px;
}
.imports-upload{
    padding:16px 20px;
    display:grid;
    grid-template-columns:minmax(220px,.65fr) minmax(320px,1.65fr);
    gap:16px;
}
.imports-upload-title{
    display:flex;
    gap:14px;
    align-items:flex-start;
}
.imports-upload h2{
    font-size:15px;
    font-weight:750;
}
.imports-upload p{
    color:#53637b;
    font-size:12px;
}
.imports-icon{
    border-radius:50%;
    background:#eaf2f8;
    color:#0870a7;
    flex:none;
    padding:10px;
    width:44px;
    height:44px;
}
.imports-icon svg{
    width:24px;
    height:24px;
}
.imports-upload-form{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
}
.imports-upload-form>div:first-of-type{
    flex:1 1 260px;
}
.imports-upload-form input[type=file]{
    font-size:12px;
    padding:7px;
    max-width:100%;
}
.imports-export-details{
    flex-basis:100%;
    order:2;
    border-top:1px solid #e0e7ee;
    padding-top:10px;
}
.imports-export-details summary{
    cursor:pointer;
    font-weight:650;
    font-size:12px;
    min-height:30px;
}
.imports-export-fields{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    padding-top:10px;
}
.imports-export-fields>label{
    grid-column:1/-1;
    font-size:12px;
    padding:10px;
}
.imports-export-fields .form-input{
    padding:8px;
}
.imports-upload-form>div[role]{
    flex-basis:100%;
    order:3;
    padding:9px 12px;
    border-width:1px;
    border-radius:6px;
    font-size:12px;
}
.imports-upload-form>div[role] p{
    margin-top:3px;
    line-height:1.5;
}
.imports-notice{
    border:1px solid #c7dffc;
    background:#eaf3ff;
    border-radius:6px;
    padding:9px 12px;
    color:#23466a;
    font-size:12px;
    line-height:1.6;
}
.imports-notice-danger{
    border-color:#fecdd3;
    background:#fff1f2;
    color:#9f1239;
}
.imports-notice ul{
    padding-left:20px;
    list-style:disc;
}
.imports-notice a{
    text-decoration:underline;
}
.imports-safety{
    margin-top:8px!important;
}
.imports-section-heading{
    margin-bottom:12px;
}
.imports-section-heading h2{
    font-size:18px;
    font-weight:750;
    letter-spacing:-.02em;
}
.imports-recent-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
}
.imports-card{
    display:flex;
    flex-direction:column;
    padding:16px;
    overflow-wrap:anywhere;
}
.imports-card-heading{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:8px;
    flex-wrap:wrap;
}
.imports-card h3{
    font-size:15px;
    line-height:1.4;
    font-weight:750;
}
.imports-type{
    color:#64748b;
    font-size:10px;
    letter-spacing:.05em;
    text-transform:uppercase;
    margin-bottom:4px;
}
.imports-status{
    display:inline-block;
    padding:4px 9px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
    line-height:1.4;
    white-space:normal;
}
.imports-status-success{
    color:#11632c;
    background:#d6f5dc;
}
.imports-status-info{
    color:#0753a4;
    background:#e2efff;
}
.imports-status-warning{
    color:#854800;
    background:#fff1bf;
}
.imports-status-danger{
    color:#a11638;
    background:#ffe1e7;
}
.imports-status-muted{
    color:#475569;
    background:#eef1f5;
}
.imports-byline{
    color:#60718a;
    font-size:11px;
    margin-top:5px;
}
.imports-metrics{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px 0;
    margin:18px 0;
    border-bottom:1px solid #dbe4ee;
    padding-bottom:16px;
}
.imports-metrics>div{
    display:flex;
    flex-direction:column-reverse;
    justify-content:flex-end;
    text-align:center;
    border-right:1px solid #e6edf4;
    padding:0 4px;
}
.imports-metrics>div:nth-child(3n){
    border:0;
}
.imports-metrics dt{
    color:#60718a;
    font-size:10px;
    line-height:1.3;
    margin-top:4px;
}
.imports-metrics dd{
    font-size:18px;
    font-weight:750;
    line-height:1.2;
}
.imports-preview{
    flex:1;
    margin-bottom:20px;
    min-height:150px;
}
.imports-preview h4{
    font-size:12px;
    font-weight:700;
    margin-bottom:8px;
}
.imports-preview h4 span{
    font-weight:400;
    color:#65748b;
    margin-left:3px;
}
.imports-preview table{
    width:100%;
    table-layout:fixed;
    font-size:12px;
    text-align:left;
}
.imports-preview th{
    background:#f0f4f8;
    padding:6px;
    font-size:11px;
}
.imports-preview th:first-child{
    width:28%;
}
.imports-preview td,.imports-preview li{
    border-bottom:1px solid #e7edf4;
    padding:7px 6px;
    font-size:12px;
}
.imports-preview td span{
    color:#52627a;
}
.imports-more{
    color:#006da1;
    font-size:12px;
    font-weight:700;
    margin-top:8px;
}
.imports-preview-note{
    color:#64748b;
    font-size:12px;
    line-height:1.6;
    margin-top:12px;
}
.imports-primary,.imports-secondary,.imports-history-action{
    display:inline-flex;
    justify-content:center;
    align-items:center;
    gap:12px;
    border-radius:5px;
    font-weight:700;
    line-height:1.3;
    min-height:40px;
    padding:8px 12px;
    text-align:center;
}
.imports-primary{
    background:#006c9f;
    color:#fff;
    border:1px solid #00638f;
}
.imports-primary:hover{
    background:#00577f;
}
.imports-secondary,.imports-history-action{
    background:#f3f6f9;
    border:1px solid #cedae7;
    color:#142e54;
    font-size:12px;
}
.imports-secondary:hover,.imports-history-action:hover{
    background:#e7eef5;
}
.imports-card-notice{
    color:#9b3041;
    background:#fff1f2;
    border:1px solid #fecdd3;
    padding:10px;
    font-size:12px;
    margin:-2px 0 14px;
    border-radius:5px;
}
.imports-progress{
    font-size:12px;
    font-weight:650;
    color:#245e48;
    margin:-2px 0 12px;
}
.imports-filter{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
    font-size:12px;
}
.imports-filter select{
    border:1px solid #cbd5e1;
    border-radius:5px;
    padding:8px;
    background:#fff;
    min-height:40px;
}
.imports-history-wrap{
    overflow:hidden;
}
.imports-history-table{
    width:100%;
    border-collapse:collapse;
    text-align:left;
    font-size:11px;
    table-layout:fixed;
}
.imports-history-table th,.imports-history-table td{
    padding:10px 8px;
    border-bottom:1px solid #e5ebf2;
    overflow-wrap:anywhere;
}
.imports-history-table thead th{
    background:#f7f9fc;
    font-size:10px;
}
.imports-history-table thead th:nth-child(2){
    width:17%;
}
.imports-history-table tbody th{
    font-weight:650;
}
.imports-history-action{
    min-height:36px;
    padding:6px 8px;
    font-size:11px;
}
.imports-subtext{
    display:block;
    color:#64748b;
    font-size:10px;
    font-weight:400;
    margin-top:4px;
}
.imports-empty{
    padding:28px;
    text-align:center;
    color:#64748b;
    grid-column:1/-1;
}
.imports-empty h3{
    font-weight:700;
    color:#142e54;
}
.imports-pagination{
    margin-top:12px;
}
.imports-footnote,.imports-filter-note{
    font-size:11px;
    line-height:1.6;
    color:#65748b;
    margin-top:10px;
}
.imports-workspace :focus-visible{
    outline:2px solid #087caf;
    outline-offset:3px;
}

@media(min-width:1550px){
    .imports-metrics{
        grid-template-columns:repeat(6,minmax(0,1fr));
    }
    .imports-metrics>div:nth-child(3n){
        border-right:1px solid #e6edf4;
    }
    .imports-metrics>div:last-child{
        border:0;
    }
}

@media(max-width:1100px){
    .imports-recent-grid{
        grid-template-columns:1fr;
    }
    .imports-card{
        padding:18px;
    }
    .imports-metrics{
        grid-template-columns:repeat(6,minmax(0,1fr));
    }
    .imports-preview{
        min-height:0;
    }
    .imports-upload{
        grid-template-columns:1fr;
    }
    .imports-history-table thead{
        position:absolute;
        width:1px;
        height:1px;
        overflow:hidden;
        clip-path:inset(50%);
    }
    .imports-history-table,.imports-history-table tbody{
        display:block;
    }
    .imports-history-table tr{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
        padding:14px;
        border-bottom:1px solid #d5dfeb;
    }
    .imports-history-table td,.imports-history-table tbody th{
        display:block;
        border:0;
        padding:3px;
        font-size:12px;
    }
    .imports-history-table td:before,.imports-history-table tbody th:before{
        content:attr(data-label);
        display:block;
        font-size:10px;
        color:#64748b;
        margin-bottom:4px;
        font-weight:400;
    }
    .imports-history-table .imports-empty{
        grid-column:1/-1;
    }
}

@media(max-width:540px){
    .imports-workspace{
        padding:16px 12px;
    }
    .imports-upload{
        padding:14px;
    }
    .imports-upload-form>div{
        flex-basis:100%;
    }
    .imports-upload-form .primary-button{
        width:100%;
    }
    .imports-export-fields{
        grid-template-columns:1fr;
    }
    .imports-metrics{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
    .imports-history-table tr{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
    .imports-history-table td:last-child{
        grid-column:1/-1;
    }
    .imports-history-action{
        width:100%;
    }
    .imports-filter{
        width:100%;
    }
    .imports-card-heading{
        gap:10px;
    }
    .imports-card{
        padding:14px;
    }
    .imports-heading .admin-title{
        font-size:27px;
    }
}

</style>
