(() => {
  'use strict';

  const body = document.body;
  document.querySelector('.sidebar-toggle')?.addEventListener('click', () => body.classList.toggle('sidebar-open'));

  document.querySelectorAll('.admin-tabs').forEach(tabBar => {
    const form = tabBar.closest('form');
    tabBar.querySelectorAll('[data-tab]').forEach(button => {
      button.addEventListener('click', () => {
        tabBar.querySelectorAll('[data-tab]').forEach(item => item.classList.remove('active'));
        form?.querySelectorAll('[data-panel]').forEach(panel => panel.classList.remove('active'));
        button.classList.add('active');
        form?.querySelector(`[data-panel="${button.dataset.tab}"]`)?.classList.add('active');
      });
    });
  });

  const updateText = (key, value) => {
    document.querySelectorAll(`[data-preview="${CSS.escape(key)}"]`).forEach(element => {
      element.textContent = value || element.dataset.fallback || '—';
    });
  };

  document.querySelectorAll('[data-live]').forEach(input => {
    const update = () => {
      let value = input.value;
      if (input.tagName === 'SELECT') value = input.options[input.selectedIndex]?.text || value;
      updateText(input.dataset.live, value);
    };
    input.addEventListener('input', update);
    input.addEventListener('change', update);
  });

  document.querySelectorAll('[data-live-color]').forEach(input => {
    const update = () => {
      const key = input.dataset.liveColor;
      document.querySelectorAll('.site-live-preview').forEach(preview => preview.style.setProperty(`--preview-${key}`, input.value));
      if (key === 'team-accent') document.querySelector('.team-admin-preview')?.style.setProperty('--team-accent', input.value);
    };
    input.addEventListener('input', update);
  });

  const setPreviewImage = (id, source) => {
    const target = document.getElementById(id);
    if (!target || !source) return;
    if (target.tagName === 'IMG') target.src = source;
    else target.style.backgroundImage = `linear-gradient(90deg,rgba(5,7,12,.92),rgba(5,7,12,.55)),url('${source.replaceAll("'", "%27")}')`;
  };

  document.querySelectorAll('[data-image-preview]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      setPreviewImage(input.dataset.imagePreview, url);
      const outputId = input.dataset.dimensionOutput;
      if (outputId) {
        const output = document.getElementById(outputId);
        const image = new Image();
        image.onload = () => {
          const sizeMb = (file.size / 1024 / 1024).toFixed(2);
          if (output) output.textContent = `${image.naturalWidth} × ${image.naturalHeight} px · ${sizeMb} MB · ${file.type || 'görsel'}`;
          URL.revokeObjectURL(url);
        };
        image.src = url;
      }
    });
  });

  document.querySelectorAll('[data-url-preview]').forEach(input => {
    const update = () => {
      if (/^https?:\/\//i.test(input.value.trim())) setPreviewImage(input.dataset.urlPreview, input.value.trim());
    };
    input.addEventListener('change', update);
    input.addEventListener('blur', update);
  });

  const playerForm = document.querySelector('form[data-used-numbers]');
  if (playerForm) {
    const teamSelect = playerForm.querySelector('[data-player-team]');
    const numberInput = playerForm.querySelector('[name="jersey_no"]');
    const status = playerForm.querySelector('[data-number-status]');
    let used = {};
    try { used = JSON.parse(playerForm.dataset.usedNumbers || '{}'); } catch { used = {}; }
    const editNumber = Number(playerForm.dataset.editNumber || 0);
    const editId = Number(playerForm.dataset.editId || 0);
    const validateNumber = () => {
      const team = String(teamSelect?.value || '');
      const number = Number(numberInput?.value || 0);
      const conflict = (used[team] || []).includes(number) && !(editId > 0 && number === editNumber);
      if (!status) return;
      status.classList.toggle('error', conflict);
      status.classList.toggle('ok', number >= 1 && number <= 99 && !conflict);
      status.textContent = conflict ? `${number} numarası bu takımda kullanılıyor.` : number >= 1 && number <= 99 ? `${number} numarası kullanılabilir.` : '1 ile 99 arasında bir numara seçin.';
    };
    numberInput?.addEventListener('input', validateNumber);
    teamSelect?.addEventListener('change', () => {
      validateNumber();
      const option = teamSelect.options[teamSelect.selectedIndex];
      const logo = option?.dataset.logo;
      const accent = option?.dataset.accent;
      if (logo) {
        document.querySelector('.player-admin-card')?.style.setProperty('--player-watermark', `url('${logo}')`);
      }
      if (accent) document.querySelector('.player-admin-card')?.style.setProperty('--player-accent', accent);
    });
    validateNumber();

    const updatePositions = () => {
      const selected = [...playerForm.querySelectorAll('[data-position]:checked')].map(input => input.value);
      document.querySelectorAll('[data-player-position-preview]').forEach(element => element.textContent = selected[0] || 'Mevki');
      document.querySelectorAll('[data-pitch-position]').forEach(marker => marker.classList.toggle('active', selected.includes(marker.dataset.pitchPosition)));
    };
    playerForm.querySelectorAll('[data-position]').forEach(input => input.addEventListener('change', updatePositions));
  }

  const eventTeam = document.querySelector('[data-event-team]');
  eventTeam?.addEventListener('change', () => {
    const option = eventTeam.options[eventTeam.selectedIndex];
    if (option?.dataset.logo) setPreviewImage('event-club-logo', option.dataset.logo);
  });

  const dateInput = document.querySelector('[data-live-date]');
  if (dateInput) {
    const updateDate = () => {
      if (!dateInput.value) return;
      const date = new Date(dateInput.value);
      const short = new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit' }).format(date);
      const full = new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date).replace(',', ' ·');
      document.querySelectorAll('[data-preview-date]').forEach(element => element.textContent = short);
      document.querySelectorAll('[data-preview-full-date]').forEach(element => element.textContent = full);
    };
    dateInput.addEventListener('input', updateDate);
  }

  const socialType = document.querySelector('[name="type"][data-live="social-type"]');
  const socialHelp = document.querySelector('[data-social-help]');
  socialType?.addEventListener('change', () => {
    const help = {
      instagram: 'Tam profil URL adresini girin: https://instagram.com/...',
      facebook: 'Tam sayfa URL adresini girin.',
      whatsapp: 'Ülke koduyla yalnız numara girin: 905xxxxxxxxx',
      email: 'Yalnız e-posta adresini girin.',
      phone: 'Görünen telefon numarasını girin.',
      address: 'Açık adres düz metin olarak gösterilir ve bağlantıya dönüşmez.'
    };
    if (socialHelp) socialHelp.textContent = help[socialType.value] || 'https:// ile başlayan tam bağlantı kullanın.';
  });

  const fieldType = document.querySelector('[name="field_type"][data-live="field-type"]');
  const fieldOptions = document.querySelector('[name="options"][data-live="field-options"]');
  const fieldPlaceholder = document.querySelector('[name="placeholder"][data-live="field-placeholder"]');
  const requiredInput = document.querySelector('[name="required"]');
  const fieldPreview = document.querySelector('[data-field-preview]');
  const renderFieldPreview = () => {
    if (!fieldPreview || !fieldType) return;
    const type = fieldType.value;
    const placeholder = fieldPlaceholder?.value || 'Örnek metin';
    const options = (fieldOptions?.value || '').split(/\r?\n/).map(item => item.trim()).filter(Boolean);
    if (type === 'textarea') fieldPreview.innerHTML = `<textarea disabled placeholder="${escapeHtml(placeholder)}"></textarea>`;
    else if (type === 'select') fieldPreview.innerHTML = `<select disabled><option>${escapeHtml(options[0] || 'Seçiniz')}</option></select>`;
    else if (type === 'multiselect') fieldPreview.innerHTML = `<div class="preview-options">${(options.length ? options : ['Seçenek 1','Seçenek 2']).slice(0,5).map(option => `<span>□ ${escapeHtml(option)}</span>`).join('')}</div>`;
    else if (type === 'checkbox') fieldPreview.innerHTML = '<div class="preview-options"><span>□ Onay seçeneği</span></div>';
    else fieldPreview.innerHTML = `<input disabled type="${['email','tel','date','number'].includes(type) ? type : 'text'}" placeholder="${escapeHtml(placeholder)}">`;
    document.querySelector('[data-required-preview]')?.replaceChildren(document.createTextNode(requiredInput?.checked ? '*' : ''));
  };
  [fieldType, fieldOptions, fieldPlaceholder, requiredInput].forEach(input => {
    input?.addEventListener('input', renderFieldPreview);
    input?.addEventListener('change', renderFieldPreview);
  });
  renderFieldPreview();

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }
})();
