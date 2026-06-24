(function () {
  'use strict';

  const cfg = window.VIKUNJA_FEATURE_REQUEST || {};
  if (!cfg.ticketId || !cfg.ajaxBase) return;

  function qs(sel, root) { return (root || document).querySelector(sel); }

  function request(path, options) {
    return fetch(cfg.ajaxBase + '/' + path, Object.assign({
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }, options || {})).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok === false) {
        throw new Error(data.error || data.message || ('HTTP ' + response.status));
      }
      return data;
    });
  }

  function buildModal() {
    const wrapper = document.createElement('div');
    wrapper.id = 'vikunja-feature-modal';
    wrapper.className = 'vf-modal-backdrop';
    wrapper.innerHTML = `
      <div class="vf-modal" role="dialog" aria-modal="true" aria-labelledby="vf-title">
        <button type="button" class="vf-close" aria-label="Close">×</button>
        <h2 id="vf-title">Move Feature Request to Vikunja</h2>
        <p>Select an existing Vikunja project, or create a new one for this request.</p>
        <label for="vf-project">Vikunja Project</label>
        <select id="vf-project"><option value="">Loading projects…</option></select>
        <label for="vf-new-project">Or create a new project</label>
        <input id="vf-new-project" type="text" placeholder="New project name">
        <div class="vf-actions">
          <button type="button" class="vf-secondary">Cancel</button>
          <button type="button" class="vf-primary">Send to Vikunja & Resolve</button>
        </div>
        <div class="vf-status" aria-live="polite"></div>
      </div>`;
    document.body.appendChild(wrapper);

    wrapper.addEventListener('click', e => {
      if (e.target === wrapper || e.target.classList.contains('vf-close') || e.target.classList.contains('vf-secondary')) closeModal();
    });
    qs('.vf-primary', wrapper).addEventListener('click', exportTicket);
    return wrapper;
  }

  function openModal() {
    let modal = qs('#vikunja-feature-modal') || buildModal();
    modal.style.display = 'flex';
    loadProjects();
  }

  function closeModal() {
    const modal = qs('#vikunja-feature-modal');
    if (modal) modal.style.display = 'none';
  }

  function setStatus(text, isError) {
    const status = qs('#vikunja-feature-modal .vf-status');
    if (!status) return;
    status.textContent = text || '';
    status.className = 'vf-status' + (isError ? ' vf-error' : '');
  }

  function loadProjects() {
    const select = qs('#vf-project');
    if (!select) return;
    select.innerHTML = '<option value="">Loading projects…</option>';
    request('projects')
      .then(data => {
        const projects = data.projects || [];
        select.innerHTML = '<option value="">Choose a project…</option>';
        projects.forEach(project => {
          const opt = document.createElement('option');
          opt.value = project.id;
          opt.textContent = project.title;
          select.appendChild(opt);
        });
        if (!projects.length) select.innerHTML = '<option value="">No projects found — create one below</option>';
      })
      .catch(err => {
        select.innerHTML = '<option value="">Unable to load projects</option>';
        setStatus(err.message, true);
      });
  }

  function exportTicket() {
    const button = qs('#vikunja-feature-modal .vf-primary');
    const projectId = qs('#vf-project').value;
    const newProjectTitle = qs('#vf-new-project').value.trim();
    if (!projectId && !newProjectTitle) {
      setStatus('Select a project or enter a new project name.', true);
      return;
    }

    button.disabled = true;
    setStatus('Sending ticket to Vikunja…');
    request('export', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket_id: cfg.ticketId, project_id: projectId, new_project_title: newProjectTitle })
    }).then(data => {
      setStatus(data.message || 'Ticket exported successfully. Reloading…');
      window.setTimeout(() => window.location.reload(), 1200);
    }).catch(err => {
      button.disabled = false;
      setStatus(err.message, true);
    });
  }

  function addButton() {
    if (qs('#vikunja-feature-button')) return;
    const target = qs('.sticky.bar .content > .pull-right.flush-right');
    if (!target) return;
    const button = document.createElement('a');
    button.id = 'vikunja-feature-button';
    button.href = '#';
    button.className = 'action-button pull-right vf-ticket-action-button';
    button.setAttribute('data-placement', 'bottom');
    button.setAttribute('data-toggle', 'tooltip');
    const buttonText = cfg.buttonText || 'Move to Projects';
    button.setAttribute('title', buttonText);
    button.textContent = buttonText;
    button.addEventListener('click', function (event) {
      event.preventDefault();
      openModal();
    });
    target.appendChild(button);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addButton);
  } else {
    addButton();
  }
})();
