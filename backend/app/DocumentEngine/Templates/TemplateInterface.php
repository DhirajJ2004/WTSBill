<?php

namespace App\DocumentEngine\Templates;

use App\DocumentEngine\Models\DocumentModel;
use App\Models\DocumentTemplate;

interface TemplateInterface
{
    public function render(DocumentModel $document, ?DocumentTemplate $templateConfig = null): string;
}
