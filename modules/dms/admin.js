/**
 * modules/dms/admin.js — ระบบจัดเก็บเอกสาร
 */
EventManager.on('router:initialized', () => {
  RouterManager.register('/dms', {
    template: 'dms/documents.html',
    title: '{LNG_List of} {LNG_Document}',
    requireAuth: true
  });
  RouterManager.register('/dms-setup', {
    template: 'dms/setup.html',
    title: '{LNG_Upload} {LNG_Document}',
    requireAuth: true
  });
  RouterManager.register('/dms-write', {
    template: 'dms/write.html',
    title: '{LNG_Document}',
    menuPath: '/dms-setup',
    requireAuth: true
  });
  RouterManager.register('/dms-files', {
    template: 'dms/files.html',
    title: '{LNG_List of} {LNG_File}',
    menuPath: '/dms-setup',
    requireAuth: true
  });
  RouterManager.register('/dms-report', {
    template: 'dms/report.html',
    title: '{LNG_Download history}',
    menuPath: '/dms-setup',
    requireAuth: true
  });
  RouterManager.register('/dms-settings', {
    template: 'dms/settings.html',
    title: '{LNG_Module Settings}',
    requireAuth: true
  });
  RouterManager.register('/dms-categories', {
    template: 'dms/categories.html',
    title: '{LNG_Cabinet}',
    requireAuth: true
  });
});

/**
 * ไอคอนชนิดไฟล์ (คอลัมน์ ext) — เอกสารแบบ URL ไม่มีไอคอน
 */
function formatDmsExt(cell, rawValue, rowData) {
  if (!rowData?.icon) {
    cell.innerHTML = '';
    return;
  }
  const ext = Utils.string.escape(rawValue || '');
  cell.innerHTML = `<img class="dms-ext" src="${Utils.string.escape(rowData.icon)}" alt="${ext}" title="${ext}">`;
}

/**
 * เครื่องหมายว่าผู้ใช้คนนี้ดาวน์โหลดไฟล์/เปิดลิงก์นี้แล้วหรือยัง
 */
function formatDmsDownloaded(cell, rawValue) {
  const done = Number(rawValue) > 0;
  cell.innerHTML = `<span class="icon-valid notext dms-downloaded${done ? ' done' : ''}" title="${Now.translate(done ? 'Downloaded' : 'Not downloaded yet')}"></span>`;
}

/**
 * ฟอร์มเอกสาร — สลับช่องแนบไฟล์/URL ตามที่เลือกใน "ต้องการ" (เหมือน initDmsWrite ของระบบเดิม)
 */
function initDmsWrite(element) {
  const want = element.querySelector('#want');
  if (!want) {
    return () => {};
  }
  const toggle = () => {
    element.querySelectorAll('[data-dms-want]').forEach(el => {
      el.style.display = el.dataset.dmsWant === want.value ? '' : 'none';
    });
  };
  want.addEventListener('change', toggle);
  toggle();

  return () => {
    want.removeEventListener('change', toggle);
  };
}
