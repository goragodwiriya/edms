<?php
/* config.php */
return [
    'version' => '7.0.4',
    'web_title' => 'eDms',
    'web_description' => 'ระบบการจัดการเอกสารอิเล็กทรอนิกส์',
    'timezone' => 'Asia/Bangkok',
    // สิทธิ์ตั้งต้นของสมาชิกใหม่ (Index\Register\Model) — ระบบเดิมตั้งไว้ที่ dms_user_permission
    'default_user_permissions' => ['can_download_dms'],
    // ระบบจัดเก็บเอกสาร (โมดูล dms)
    'dms_format_no' => '%04d',
    'dms_prefix' => 'DOC%Y%M-',
    'dms_file_typies' => ['doc', 'ppt', 'pptx', 'docx', 'rar', 'zip', 'jpg', 'pdf'],
    'dms_upload_size' => 2097152,
    'dms_download_action' => 0,
    'dms_upload_options' => 0
];
