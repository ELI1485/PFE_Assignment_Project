<?php require 'vendor/autoload.php'; \ = new \PhpOffice\PhpWord\TemplateProcessor('storage/template_pv.docx'); \->setValue('department_name', 'Info & Maths'); \->saveAs('storage/test.docx');
