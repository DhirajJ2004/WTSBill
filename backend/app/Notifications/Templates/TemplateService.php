<?php

namespace App\Notifications\Templates;

use App\Models\NotificationTemplate;
use InvalidArgumentException;

class TemplateService
{
    /**
     * Ensure default notification templates exist for the company.
     */
    public static function ensureDefaultTemplates(int $companyId): void
    {
        $defaults = DefaultTemplates::getDefaults();
        foreach ($defaults as $tmpl) {
            NotificationTemplate::firstOrCreate(
                ['company_id' => $companyId, 'template_key' => $tmpl['template_key'], 'channel' => $tmpl['channel']],
                [
                    'name' => $tmpl['name'],
                    'category' => $tmpl['category'],
                    'subject' => $tmpl['subject'],
                    'body_template' => $tmpl['body_template'],
                    'variables_json' => $tmpl['variables_json'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Render template with variables.
     * Replaces {{variable_name}} safely, preventing code execution or 'null'/'undefined' outputs.
     */
    public static function render(string $templateText, array $variables = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            if (isset($variables[$key]) && $variables[$key] !== null) {
                return htmlspecialchars((string)$variables[$key], ENT_QUOTES, 'UTF-8');
            }
            return ''; // Graceful empty fallback instead of null or undefined
        }, $templateText);
    }

    /**
     * Render subject line safely.
     */
    public static function renderSubject(?string $subjectTemplate, array $variables = []): ?string
    {
        if (!$subjectTemplate) return null;
        return self::render($subjectTemplate, $variables);
    }

    /**
     * Preview template rendering with sample data.
     */
    public static function preview(int $templateId, array $customSampleData = []): array
    {
        $template = NotificationTemplate::findOrFail($templateId);

        $defaultSample = [
            'customer_name' => 'Acme Technologies Ltd',
            'invoice_number' => 'INV-2026-0042',
            'invoice_total' => '₹ 25,000.00',
            'balance_due' => '₹ 15,000.00',
            'due_date' => '31/08/2026',
            'days_overdue' => '7',
            'business_name' => 'Bharat Apex Enterprises',
            'payment_link' => 'https://pay.wtsbill.in/plink_sample123',
            'receipt_number' => 'REC-2026-0089',
            'amount_paid' => '₹ 10,000.00',
            'payment_date' => date('d/m/Y'),
            'product_name' => 'Dell Latitude 3520 Laptop',
            'sku' => 'DELL-3520',
            'current_stock' => '2',
            'minimum_stock' => '10',
            'transfer_number' => 'TRF-PUN-MUM-001',
            'total_quantity' => '40',
            'destination_warehouse' => 'Mumbai Main Warehouse',
        ];

        $mergedData = array_merge($defaultSample, $customSampleData);

        return [
            'template_id' => $template->id,
            'template_key' => $template->template_key,
            'name' => $template->name,
            'channel' => $template->channel,
            'category' => $template->category,
            'is_sample_preview' => true,
            'rendered_subject' => self::renderSubject($template->subject, $mergedData),
            'rendered_body' => self::render($template->body_template, $mergedData),
            'sample_variables_used' => $mergedData,
        ];
    }
}
