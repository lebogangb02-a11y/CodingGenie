/* Real-time Admin Dashboard Metrics (Hostinger-friendly)
 * - Polls JSON APIs every 8 seconds (configurable)
 * - Per-endpoint AbortController to avoid overlapping requests
 * - Loading shimmer, fade-in counters, and error fallbacks
 * - Extends to Email Logs, Audit Logs (pagination), and System Health
 */
(function(){
  const REFRESH_MS = (window.dashboardRefreshMs || 8000);
  const els = {
    total: document.getElementById('stat-total'),
    pending: document.getElementById('stat-pending'),
    approved: document.getElementById('stat-approved'),
    rejected: document.getElementById('stat-rejected'),
    today: document.getElementById('stat-today'),
    month: document.getElementById('stat-month'),
    year: document.getElementById('stat-year'),
    recent: document.getElementById('recent-activity'),
    dbBadge: document.getElementById('db-status-badge'),
    tablesBadge: document.getElementById('tables-count-badge'),
    notice: document.getElementById('live-notice'),
    emailLogs: document.getElementById('email-logs'),
    auditLogs: document.getElementById('audit-logs'),
    auditPrev: document.getElementById('audit-prev'),
    auditNext: document.getElementById('audit-next'),
    auditPage: document.getElementById('audit-page'),
    healthLatency: document.getElementById('health-latency'),
    healthTables: document.getElementById('health-tables'),
    healthStatus: document.getElementById('health-status')
  };

  const state = { lastTotal: null, auditPage: 1, auditTotalPages: 1 };
  const controllers = { stats:null, activity:null, emails:null, audit:null, health:null };

  function setText(el, val){
    if (!el) return;
    const old = el.textContent.trim();
    const next = String(val);
    if (old === next) return;
    el.classList.remove('fade-in');
    el.style.opacity = '0.3';
    el.textContent = next;
    setTimeout(()=>{ el.classList.add('fade-in'); el.style.opacity = '1'; }, 30);
  }

  function setBadge(el, label, cls){
    if (!el) return;
    el.textContent = label;
    el.className = 'badge ' + cls;
  }

  function renderRecent(list){
    if (!els.recent) return;
    if (!Array.isArray(list) || list.length === 0){
      els.recent.innerHTML = '<div class="text-muted">No recent activity</div>';
      return;
    }
    const html = list.map(r => (
      `<div class="d-flex align-items-start mb-2">
         <i class="bi bi-clock-history me-2 text-secondary"></i>
         <div>
           <div><strong>${escapeHtml(r.admin_username || 'admin')}</strong> — ${escapeHtml(r.action || '')}</div>
           ${r.details ? `<div class="small text-muted">${escapeHtml(r.details)}</div>` : ''}
           <div class="small text-muted">${escapeHtml(r.created_at || '')}</div>
         </div>
       </div>`
    )).join('');
    els.recent.innerHTML = html;
  }

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[m];
    });
  }

  async function fetchJSON(url, key, retries=1){
    try{
      if (controllers[key]) controllers[key].abort();
      controllers[key] = new AbortController();
      const res = await fetch(url, { signal: controllers[key].signal, cache: 'no-store' });
      return await res.json();
    }catch(e){
      if (retries > 0) {
        await new Promise(r => setTimeout(r, 500));
        return fetchJSON(url, key, retries-1);
      }
      return { success:false, error: e.message };
    }
  }

  async function refresh(){
    // Loading hint
    [els.total,els.pending,els.approved,els.rejected,els.today,els.month,els.year].forEach(el=>{ if(el) el.classList.add('placeholder-glow'); });

    const stats = await fetchJSON('api/getStats.php','stats');
    if (stats && stats.success){
      const d = stats.data || {};
      setText(els.total, d.total ?? '-');
      setText(els.pending, d.pending ?? '-');
      setText(els.approved, d.approved ?? '-');
      setText(els.rejected, d.rejected ?? '-');
      setText(els.today, d.today ?? '-');
      setText(els.month, d.month ?? '-');
      setText(els.year, d.year ?? '-');

      if (d.health){
        setBadge(els.dbBadge, d.health.dbOnline ? 'Online' : 'Offline', d.health.dbOnline ? 'bg-success' : 'bg-danger');
        if (typeof d.health.totalTables === 'number' && els.tablesBadge){ els.tablesBadge.textContent = String(d.health.totalTables); }
      }

      // Live new submission notice
      if (typeof d.total === 'number'){
        if (state.lastTotal !== null && d.total > state.lastTotal){
          if (els.notice){
            els.notice.innerHTML = `<div class="alert alert-info py-2 mb-2"><i class="bi bi-bell me-2"></i>New application submitted (+${d.total - state.lastTotal})</div>`;
            setTimeout(()=>{ els.notice.innerHTML = ''; }, 6000);
          }
        }
        state.lastTotal = d.total;
      }
    }

    const activity = await fetchJSON('api/getRecentActivity.php?limit=10','activity');
    if (activity && activity.success){
      renderRecent(activity.data);
    }

    // Email logs
    if (els.emailLogs){
      els.emailLogs.classList.add('placeholder-glow');
      const emails = await fetchJSON('api/getEmailLogs.php?limit=10','emails');
      if (emails && emails.success){ renderEmailLogs(emails.data); }
      els.emailLogs.classList.remove('placeholder-glow');
    }

    // Audit logs with pagination
    if (els.auditLogs){
      els.auditLogs.classList.add('placeholder-glow');
      const audit = await fetchJSON(`api/getAuditLogs.php?page=${state.auditPage}&limit=10`,'audit');
      if (audit && audit.success){
        state.auditTotalPages = audit.total_pages || 1;
        renderAuditLogs(audit.data, state.auditPage, state.auditTotalPages);
      }
      els.auditLogs.classList.remove('placeholder-glow');
    }

    // System health monitor
    if (els.healthLatency || els.healthTables || els.healthStatus){
      const health = await fetchJSON('api/getSystemHealth.php','health');
      if (health && health.success){ updateHealth(health.data); }
      else { updateHealth({ db_online:false, table_count:'-', response_ms:'-' }); }
    }

    [els.total,els.pending,els.approved,els.rejected,els.today,els.month,els.year].forEach(el=>{ if(el) el.classList.remove('placeholder-glow'); });
  }

  // Initial fetch and interval
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
  } else {
    refresh();
  }
  setInterval(refresh, REFRESH_MS);

  // Online/offline signals
  window.addEventListener('offline', ()=>{
    if (els.healthStatus){ els.healthStatus.textContent = 'Offline'; els.healthStatus.className = 'badge bg-danger'; }
  });
  window.addEventListener('online', ()=>{ refresh(); });

  // Renderers
  function renderEmailLogs(list){
    if (!els.emailLogs) return;
    if (!Array.isArray(list) || list.length === 0){
      els.emailLogs.innerHTML = '<div class="text-muted">No recent emails</div>';
      return;
    }
    const html = list.map(r => `
      <div class="d-flex justify-content-between border-bottom py-1">
        <div>
          <strong>${escapeHtml(r.subject || 'Email')}</strong>
          <div class="small text-muted">App #${escapeHtml(r.application_id ?? '')}</div>
        </div>
        <div class="text-end">
          <span class="badge ${String(r.status).toLowerCase()==='unread'?'bg-warning':'bg-success'}">${escapeHtml(r.status ?? '')}</span>
          <div class="small text-muted">${escapeHtml(r.created_at || '')}</div>
        </div>
      </div>
    `).join('');
    els.emailLogs.innerHTML = html;
  }

  function renderAuditLogs(list, page, totalPages){
    if (!els.auditLogs) return;
    if (!Array.isArray(list) || list.length === 0){
      els.auditLogs.innerHTML = '<div class="text-muted">No audit logs</div>';
      return;
    }
    const html = list.map(r => `
      <div class="d-flex align-items-start mb-2">
        <i class="bi bi-shield-check me-2 text-secondary"></i>
        <div>
          <div><strong>${escapeHtml(r.admin_username || 'admin')}</strong> — ${escapeHtml(r.action || '')}</div>
          ${r.details ? `<div class="small text-muted">${escapeHtml(r.details)}</div>` : ''}
          <div class="small text-muted">${escapeHtml(r.created_at || '')}${r.ip_address?` · ${escapeHtml(r.ip_address)}`:''}</div>
        </div>
      </div>
    `).join('');
    els.auditLogs.innerHTML = html + `<div class="d-flex justify-content-between mt-2">
      <button id="audit-prev" class="btn btn-sm btn-outline-secondary" ${page<=1?'disabled':''}>Prev</button>
      <span id="audit-page" class="small text-muted">Page ${page} / ${totalPages}</span>
      <button id="audit-next" class="btn btn-sm btn-outline-secondary" ${page>=totalPages?'disabled':''}>Next</button>
    </div>`;

    // Rebind controls
    const prev = document.getElementById('audit-prev');
    const next = document.getElementById('audit-next');
    if (prev) prev.onclick = () => { if (state.auditPage>1){ state.auditPage--; refresh(); } };
    if (next) next.onclick = () => { if (state.auditPage<state.auditTotalPages){ state.auditPage++; refresh(); } };
  }

  function updateHealth(d){
    if (els.healthStatus){
      const ok = !!d.db_online;
      els.healthStatus.textContent = ok ? 'Online' : 'Offline';
      els.healthStatus.className = 'badge ' + (ok ? 'bg-success' : 'bg-danger');
    }
    if (els.healthTables){ els.healthTables.textContent = String(d.table_count ?? '-'); }
    if (els.healthLatency){ els.healthLatency.textContent = String(d.response_ms ?? '-'); }
  }
})();