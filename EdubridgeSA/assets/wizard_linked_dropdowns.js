// Searchable select dropdown for High School, with integrated search box
(function(){
  'use strict';

  // Inject minimal validation CSS to show immediate green ticks
  (function injectValidationCSS(){
    if (document.getElementById('wizard-validation-css')) return;
    var css = [
      '.form-select.is-valid, .form-control.is-valid{border-color:#198754 !important;}',
      '.form-select.is-invalid, .form-control.is-invalid{border-color:#dc3545 !important;}',
      // Tick icon for selects (native and custom button-form-select)
      '.form-select.is-valid{padding-right:2.25rem;background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'%3E%3Cpath fill=\'#198754\' d=\'M6.173 13.114L1.657 8.6l1.415-1.414 3.101 3.101 6.364-6.364 1.414 1.414-7.778 7.778z\'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:1rem 1rem;}',
      'button.form-select.is-valid{border-color:#198754 !important;padding-right:2.25rem;background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 16 16\'%3E%3Cpath fill=\'#198754\' d=\'M6.173 13.114L1.657 8.6l1.415-1.414 3.101 3.101 6.364-6.364 1.414 1.414-7.778 7.778z\'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:1rem 1rem;}'
    ].join('\n');
    var style = document.createElement('style');
    style.id = 'wizard-validation-css';
    style.textContent = css;
    document.head.appendChild(style);
  })();

  var schoolProvince = document.getElementById('school_province');
  var highSchoolSel  = document.getElementById('high_school_name');
  var initSchool     = (window.INIT_SCHOOL || '').trim();
  var allSchools     = [];

  // Helper: toggle valid/invalid classes based on value presence
  function applyValidClass(el){
    if(!el) return false;
    var ok = !!String(el.value||'').trim();
    try{
      el.classList.toggle('is-valid', ok);
      el.classList.toggle('is-invalid', !ok);
    }catch(e){}
    // If element has a custom toggle, mirror classes
    var t = el.__customToggle;
    if(t){
      try{
        t.classList.toggle('is-valid', ok);
        t.classList.toggle('is-invalid', !ok);
      }catch(e){}
    }
    return ok;
  }

  function escapeHtml(str){
    var map = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'};
    return String(str || '').replace(/[&<>"']/g, function(c){ return map[c] || c; });
  }
  function compareNames(a,b){
    var ta = String(a && a.name || '').toLowerCase();
    var tb = String(b && b.name || '').toLowerCase();
    if(ta < tb) return -1; if(ta > tb) return 1; return 0;
  }

  function buildSearchableSelect(selectEl){
    if(!selectEl) return null;

    var wrapper = document.createElement('div');
    wrapper.className = 'position-relative';

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'form-select text-start';
    toggle.setAttribute('aria-haspopup','listbox');
    toggle.style.cursor = 'pointer';

    var panel = document.createElement('div');
    panel.className = 'border bg-white shadow-sm rounded position-absolute w-100';
    panel.style.zIndex = '1000';
    panel.style.maxHeight = '280px';
    panel.style.overflowY = 'auto';
    panel.style.display = 'none';

    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'form-control border-0 border-bottom rounded-0';
    searchInput.placeholder = 'Type to search…';

    var list = document.createElement('div');
    list.setAttribute('role','listbox');

    var noRes = document.createElement('div');
    noRes.className = 'p-2 text-muted';
    noRes.textContent = 'No results';
    noRes.style.display = 'none';

    panel.appendChild(searchInput);
    panel.appendChild(list);
    panel.appendChild(noRes);

    // Hide native select for custom UI, keep for submission/validation
    selectEl.style.display = 'none';
    var placeholder = 'Select High School';
    toggle.innerHTML = escapeHtml(selectEl.value || initSchool || '');
    if(!toggle.textContent.trim()) toggle.textContent = placeholder;

    selectEl.parentNode.insertBefore(wrapper, selectEl);
    wrapper.appendChild(toggle);
    wrapper.appendChild(panel);

    // Expose toggle on the native select for global validity helpers
    selectEl.__customToggle = toggle;

    var choices = [];

    function ensureNativeOption(val){
      var v = String(val||'');
      var found = false;
      for(var i=0;i<selectEl.options.length;i++){
        if(String(selectEl.options[i].value) === v){ found = true; break; }
      }
      if(!found && v){
        var opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        selectEl.appendChild(opt);
      }
      if(v){
        selectEl.value = v;
        if(selectEl.selectedIndex === -1 && selectEl.options.length){
          // select the last appended option if direct value assignment failed
          selectEl.options[selectEl.options.length-1].selected = true;
        }
      }
    }

    // Helper text under the native select (if present)
    var helperText = (function(){
      try { return selectEl.parentNode.querySelector('.form-text'); } catch(e){ return null; }
    })();

    // Simple success toast
    function showToast(msg){
      var text = String(msg||'Saved ✓');
      var toast = document.createElement('div');
      toast.textContent = text;
      toast.style.position = 'fixed';
      toast.style.left = '50%';
      toast.style.bottom = '20px';
      toast.style.transform = 'translateX(-50%)';
      toast.style.background = '#198754';
      toast.style.color = '#fff';
      toast.style.padding = '8px 14px';
      toast.style.borderRadius = '6px';
      toast.style.boxShadow = '0 4px 10px rgba(0,0,0,0.15)';
      toast.style.zIndex = '2000';
      toast.style.fontSize = '0.95rem';
      toast.setAttribute('role','status');
      toast.setAttribute('aria-live','polite');
      document.body.appendChild(toast);
      setTimeout(function(){ try{ document.body.removeChild(toast); }catch(e){} }, 1200);
    }

    // Commit typed/selected value to native select, hide helper and show toast
    function commitSelection(val){
      var v = String(val||'').trim();
      if(!v) return;
      ensureNativeOption(v);
      toggle.innerHTML = escapeHtml(v);
      selectEl.setCustomValidity('');
      panel.style.display = 'none';
      if(helperText){ helperText.style.display = 'none'; }
      // Fire change for any downstream listeners
      try { selectEl.dispatchEvent(new Event('change', {bubbles:true})); } catch(e){}
      // Mark valid immediately and update form progression state
      applyValidClass(selectEl);
      updateNextState();
      showToast('Saved ✓');
    }

    function render(items){
      list.innerHTML = '';
      if(!items || items.length === 0){
        noRes.style.display = 'block';
        return;
      }
      noRes.style.display = 'none';
      for(var i=0;i<items.length;i++){
        var it = items[i];
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dropdown-item w-100 text-truncate';
        btn.textContent = String(it && it.name || '');
        btn.addEventListener('click', function(ev){
          var val = ev.currentTarget.textContent;
          commitSelection(val);
        });
        list.appendChild(btn);
      }
    }

    async function filter(query){
      var q = String(query||'').trim().toLowerCase();
      var out = (!q) ? choices : choices.filter(function(s){
        return String(s && s.name || '').toLowerCase().indexOf(q) !== -1;
      });
      if(q && (!Array.isArray(choices) || choices.length === 0)){
        try{
          var rawProv = (schoolProvince && schoolProvince.value) ? String(schoolProvince.value).trim() : '';
          var pl = rawProv.toLowerCase();
          var isAll = (!rawProv) || pl === 'all provinces' || pl === 'all' || pl === 'any' || pl === 'all-za';
          var provParam = isAll ? '' : ('&province='+encodeURIComponent(rawProv));
          var res = await fetch('api/catalog.php?type=schools'+provParam+'&q='+encodeURIComponent(q));
          var data = await res.json();
          var items = (data.items || data) || [];
          out = items.slice().sort(compareNames);
        }catch(e){ console.error('School search fallback failed', e); }
      }
      render(out);
    }

    toggle.addEventListener('click', function(){
      var showing = panel.style.display === 'block';
      panel.style.display = showing ? 'none' : 'block';
      if(!showing){
        searchInput.value='';
        filter('');
        setTimeout(function(){ searchInput.focus(); }, 0);
      }
    });
    document.addEventListener('click', function(ev){
      if(!wrapper.contains(ev.target)) panel.style.display = 'none';
    });
    searchInput.addEventListener('input', function(){ filter(searchInput.value); });
    // Save on Enter key or when leaving the search field (prefer first match)
    searchInput.addEventListener('keydown', function(e){
      if(e.key === 'Enter'){
        e.preventDefault();
        var first = list.querySelector('.dropdown-item');
        if(first){ commitSelection(first.textContent); }
        else { commitSelection(searchInput.value); }
      }
    });
    searchInput.addEventListener('blur', function(){
      var v = String(searchInput.value||'').trim();
      if(!v) return;
      var first = list.querySelector('.dropdown-item');
      if(first){ commitSelection(first.textContent); }
      else { commitSelection(v); }
    });

    // Keep native select and validity in sync when value changes elsewhere
    selectEl.addEventListener('change', function(){
      var val = String(selectEl.value||'').trim();
      if(val){ toggle.innerHTML = escapeHtml(val); selectEl.setCustomValidity(''); }
      else { toggle.textContent = placeholder; }
      applyValidClass(selectEl);
      updateNextState();
    });

    return {
      updateChoices: function(items){ choices = (items||[]).slice().sort(compareNames); render(choices); },
      setSelectedByName: function(name){
        var val = String(name||'');
        if(val){ ensureNativeOption(val); toggle.innerHTML = escapeHtml(val); selectEl.value = val; if(helperText){ helperText.style.display='none'; } }
        else { toggle.textContent = placeholder; selectEl.value = ''; }
        applyValidClass(selectEl);
        updateNextState();
      }
    };
  }

  var widget = buildSearchableSelect(highSchoolSel);

  // Form and Next button state shared across helpers
  var form = document.querySelector('form.needs-validation');
  var nextBtn = null;
  function updateNextState(){
    try{
      if(!form || !nextBtn) return;
      var req = form.querySelectorAll('input[required], select[required]');
      var allValid = true;
      req.forEach(function(el){ if(!applyValidClass(el)) allValid = false; });
      nextBtn.disabled = !allValid;
    }catch(e){ /* no-op */ }
  }

  async function fetchSchools(){
    var rawProv = (schoolProvince && schoolProvince.value) ? String(schoolProvince.value).trim() : '';
    var pl = rawProv.toLowerCase();
    var isAll = (!rawProv) || pl === 'all provinces' || pl === 'all' || pl === 'any' || pl === 'all-za';
    var provParam = isAll ? '' : ('&province='+encodeURIComponent(rawProv));
    try{
      var res = await fetch('api/catalog.php?type=schools'+provParam);
      var data = await res.json();
      var items = (data.items || data) || [];
      allSchools = items.slice().sort(compareNames);
      if(widget){ widget.updateChoices(allSchools); widget.setSelectedByName(initSchool); }
    }catch(e){ console.error('Failed to load schools', e); }
  }

  if(schoolProvince){
    schoolProvince.addEventListener('change', function(){ fetchSchools(); });
  }

  fetchSchools();

  // -----------------------------
  // Universities and Courses wiring for choices 1–3
  // -----------------------------
  var typeSel     = document.getElementById('institution_type');
  var allUniversities = [];

  function setSelectOptions(selectEl, items, getText, placeholder, initVal, getValue){
    if (!selectEl) return;
    var previous = (selectEl.value || '').trim();
    var desired  = (initVal || previous || '').trim();
    while (selectEl.options && selectEl.options.length) { selectEl.remove(0); }
    var opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder || 'Select';
    selectEl.appendChild(opt0);
    (items || []).forEach(function(item){
      var text = (typeof getText === 'function') ? getText(item) : (item && item.name) || String(item || '');
      if (!text) return;
      var opt = document.createElement('option');
      var val = (typeof getValue === 'function') ? getValue(item) : text;
      opt.value = val;
      opt.textContent = text;
      selectEl.appendChild(opt);
    });
    if (desired) { selectEl.value = desired; }
  }

  var choices = [
    { uni: document.getElementById('institution_choice_1'), course: document.getElementById('program_choice_1'), category: document.getElementById('course_category'), initUni: (window.INIT_UNI||'').trim(), initCourse: (window.INIT_COURSE||'').trim(), specInput: document.getElementById('program_specialization_1'), specListId: 'spec_list_1', dupMsg: document.getElementById('dup_msg_1') },
    { uni: document.getElementById('institution_choice_2'), course: document.getElementById('program_choice_2'), category: document.getElementById('course_category_2'), initUni: (window.INIT_UNI_2||'').trim(), initCourse: (window.INIT_COURSE_2||'').trim(), specInput: document.getElementById('program_specialization_2'), specListId: 'spec_list_2', dupMsg: document.getElementById('dup_msg_2') },
    { uni: document.getElementById('institution_choice_3'), course: document.getElementById('program_choice_3'), category: document.getElementById('course_category_3'), initUni: (window.INIT_UNI_3||'').trim(), initCourse: (window.INIT_COURSE_3||'').trim(), specInput: document.getElementById('program_specialization_3'), specListId: 'spec_list_3', dupMsg: document.getElementById('dup_msg_3') }
  ];

  async function loadUniversities(){
    // Only run when at least one select exists
    var anyUni = choices.some(function(c){ return !!c.uni; });
    if (!anyUni) return;
    try {
      var res = await fetch('api/catalog.php?type=universities');
      var data = await res.json();
      var items = (data.items || data) || [];
      items.sort(function(a,b){
        var ta = String(a && a.name || '').toLowerCase();
        var tb = String(b && b.name || '').toLowerCase();
        if(ta < tb) return -1; if(ta > tb) return 1; return 0;
      });
      allUniversities = items;
      renderUniversitiesForAll();
    } catch (e){ console.error('Failed to load universities', e); }
  }

  function renderUniversitiesForAll(){
    var type = (typeSel && (typeSel.value || '').toLowerCase()) || '';
    var list = allUniversities.slice();
    if (type && type !== 'all') {
      list = list.filter(function(u){ return String((u && u.type) || 'public').toLowerCase() === type; });
    }
    choices.forEach(function(c){
      if (!c.uni) return;
      setSelectOptions(c.uni, list, function(u){ return u.name; }, 'Select University', c.initUni);
      applyValidClass(c.uni);
    });
    updateNextState();
  }

  async function loadCoursesForChoice(c){
    if (!c || !c.course) return;
    try {
      var uni = (c.uni && c.uni.value) ? c.uni.value : '';
      var cat = (c.category && c.category.value) ? c.category.value : '';
      var url = 'api/catalog.php?type=courses' + (uni ? ('&university='+encodeURIComponent(uni)) : '') + (cat ? ('&category='+encodeURIComponent(cat)) : '');
      var res = await fetch(url);
      var data = await res.json();
      var items = (data.items || data) || [];
      items.sort(function(a,b){
        var an = String((a && (a.course || a.name)) || '').toLowerCase();
        var bn = String((b && (b.course || b.name)) || '').toLowerCase();
        if (an < bn) return -1; if (an > bn) return 1; return 0;
      });
      setSelectOptions(c.course, items, function(ci){ return ci.course || ci.name || ''; }, 'Select Course', c.initCourse);
      applyValidClass(c.course);
      updateNextState();
      // Load specialization suggestions
      attachSpecializations(c);
    } catch (e){ console.error('Failed to load courses', e); }
  }

  // Format specialization display labels to be concise
  function prettySpecializationName(name){
    var n = String(name || '');
    // Remove trailing "Education" suffix (e.g., "Mathematics Education" -> "Mathematics")
    n = n.replace(/\s*Education\s*$/i, '');
    return n.trim();
  }

  function attachSpecializations(c){
    if (!c || !c.specInput) return;
    var courseVal = (c.course && c.course.value) ? c.course.value : '';
    var catVal = (c.category && c.category.value) ? c.category.value : '';
    var url = 'api/catalog.php?type=specializations' + (courseVal ? ('&course='+encodeURIComponent(courseVal)) : '') + (catVal ? ('&category='+encodeURIComponent(catVal)) : '');
    fetch(url).then(function(res){ return res.json(); }).then(function(data){
      var items = (data.items || data) || [];
      var specEl = c.specInput;
      if (!specEl) return;
      var tag = (specEl.tagName || '').toLowerCase();
      if (tag === 'select') {
        // Populate a select dropdown; handle empty states
        setSelectOptions(
          specEl,
          items,
          function(s){ return prettySpecializationName((s && s.name) || ''); },
          'Select Specialization',
          '',
          function(s){ return (s && s.name) || ''; }
        );
        // Disable if no items
        if (!items || items.length === 0) {
          specEl.disabled = true;
        } else {
          specEl.disabled = false;
        }
        applyValidClass(specEl);
      } else {
        // Fallback: populate datalist for input[list]
        var listId = c.specListId || specEl.getAttribute('list');
        var dl = listId ? document.getElementById(listId) : null;
        if (!dl) return;
        dl.innerHTML = '';
        if (!items || items.length === 0) {
          var opt = document.createElement('option');
          opt.value = 'No specializations available';
          dl.appendChild(opt);
        } else {
          items.forEach(function(s){
            var opt = document.createElement('option');
            // Keep original value for mapping; show concise label in UI components
            opt.value = String(s && s.name || '');
            dl.appendChild(opt);
          });
        }
      }
    }).catch(function(e){ console.error('Failed to load specializations', e); });
  }

  function getCombos(){
    return choices.map(function(c){
      return {
        uni: (c.uni && c.uni.value ? c.uni.value.trim() : ''),
        course: (c.course && c.course.value ? c.course.value.trim() : '')
      };
    });
  }

  function checkDuplicates(changedIndex){
    var combos = getCombos();
    var seen = {};
    var dupFound = false;
    combos.forEach(function(cb, idx){
      if (!cb.uni || !cb.course) return;
      var key = (cb.uni.toLowerCase()+'|'+cb.course.toLowerCase());
      if (seen[key] !== undefined) {
        dupFound = true;
        // mark both selects invalid and show message under course for changedIndex or target
        var targetIdx = (typeof changedIndex === 'number') ? changedIndex : idx;
        var c = choices[targetIdx];
        if (c && c.course) {
          c.course.setCustomValidity('Duplicate selection: same university and course already chosen.');
          applyValidClass(c.course);
          var msg = c.dupMsg || null;
          if (msg) { msg.textContent = 'Duplicate selection. Choose a different combination.'; msg.style.display = 'block'; }
        }
      } else {
        seen[key] = idx;
      }
    });
    if (!dupFound) {
      choices.forEach(function(c){
        if (!c || !c.course) return;
        c.course.setCustomValidity('');
        var msg = c.dupMsg || null; if (msg) msg.style.display = 'none';
        applyValidClass(c.course);
      });
    }
    updateNextState();
  }

  // Event wiring per choice
  choices.forEach(function(c, idx){
    if (!c.uni && !c.course) return;
    // On university change, reload courses for this choice
    if (c.uni) {
      c.uni.addEventListener('change', function(){ applyValidClass(c.uni); c.initCourse=''; loadCoursesForChoice(c); checkDuplicates(idx); });
    }
    // On category change, reload courses for this choice
    if (c.category) {
      c.category.addEventListener('change', function(){ c.initCourse=''; loadCoursesForChoice(c); });
    }
    // On course change, attach specializations and validate duplicates
    if (c.course) {
      c.course.addEventListener('change', function(){ attachSpecializations(c); checkDuplicates(idx); });
    }
  });

  // Type filter affects all university selects
  if (typeSel) { typeSel.addEventListener('change', renderUniversitiesForAll); }

  // Initial load
  loadUniversities();
  choices.forEach(function(c){ if (c.course) loadCoursesForChoice(c); });

  // Submit flow: ensure school selection is recognized before Next
  if(form){
    // Enable/disable Next button based on required field validity
    nextBtn = form.querySelector('button[name="action"][value="next"]');

    // Real-time validity and Next state for all required fields
    var reqFields = form.querySelectorAll('input[required], select[required]');
    reqFields.forEach(function(el){
      var ev = (el.tagName && el.tagName.toLowerCase() === 'select') ? 'change' : 'input';
      el.addEventListener(ev, function(){ applyValidClass(el); updateNextState(); });
      // Initial mark
      applyValidClass(el);
    });
    // Initial button state
    updateNextState();

    if(nextBtn){
      nextBtn.addEventListener('click', function(ev){
        // Sync validity for school select
        if(highSchoolSel){ highSchoolSel.dispatchEvent(new Event('change', {bubbles:true})); }
        // Respect built-in validation
        if(!form.checkValidity()){
          ev.preventDefault(); ev.stopPropagation();
          form.classList.add('was-validated');
          return;
        }
        // Ensure the clicked button contributes its name/value (action=next)
        if(typeof form.requestSubmit === 'function'){
          ev.preventDefault();
          try{ form.requestSubmit(nextBtn); return; }catch(e){ /* fallback below */ }
        }
        // Fallback: guarantee action=next and submit even if requestSubmit not available
        try {
          var auto = form.querySelector('input[name="action"][data-auto="true"]');
          if(!auto){
            auto = document.createElement('input');
            auto.type = 'hidden';
            auto.name = 'action';
            auto.value = 'next';
            auto.setAttribute('data-auto','true');
            form.appendChild(auto);
          } else {
            auto.value = 'next';
          }
          // Allow default or force submit
          form.submit();
        } catch(err){ /* if submit fails, default browser submission will proceed */ }
      });
    }
  }
})();