<?php
echo json_encode(['files' => $_FILES, 'post_max_size' => ini_get('post_max_size'), 'upload_max_filesize' => ini_get('upload_max_filesize')]);
