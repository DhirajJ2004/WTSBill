<?php

namespace App\Notifications\Templates;

class DefaultTemplates
{
    public static function getDefaults(): array
    {
        return [
            [
                'template_key' => 'PAYMENT_REMINDER',
                'name' => 'Upcoming Payment Reminder',
                'channel' => 'EMAIL',
                'category' => 'PAYMENTS',
                'subject' => 'Payment Reminder for Invoice {{invoice_number}} - {{business_name}}',
                'body_template' => "Dear {{customer_name}},\n\nThis is a friendly reminder that payment for Invoice {{invoice_number}} totaling {{invoice_total}} is due on {{due_date}}.\n\nOutstanding Balance: {{balance_due}}\n\nPlease arrange for payment before the due date.\n\nThank you,\n{{business_name}}",
                'variables_json' => ['customer_name', 'invoice_number', 'invoice_total', 'balance_due', 'due_date', 'business_name', 'payment_link'],
            ],
            [
                'template_key' => 'PAYMENT_REMINDER_WA',
                'name' => 'Upcoming Payment Reminder (WhatsApp)',
                'channel' => 'WHATSAPP',
                'category' => 'PAYMENTS',
                'subject' => null,
                'body_template' => "Hello {{customer_name}}, friendly reminder from {{business_name}}: Invoice *{{invoice_number}}* of *{{balance_due}}* is due on *{{due_date}}*. Kindly process at your earliest convenience.",
                'variables_json' => ['customer_name', 'invoice_number', 'balance_due', 'due_date', 'business_name'],
            ],
            [
                'template_key' => 'OVERDUE_PAYMENT',
                'name' => 'Overdue Invoice Notice',
                'channel' => 'EMAIL',
                'category' => 'PAYMENTS',
                'subject' => 'Overdue Notice: Invoice {{invoice_number}} is {{days_overdue}} days overdue',
                'body_template' => "Dear {{customer_name}},\n\nOur records indicate that Invoice {{invoice_number}} (Due Date: {{due_date}}) is now overdue by {{days_overdue}} days.\n\nOutstanding Balance Due: {{balance_due}}\n\nPlease settle this outstanding balance or contact us if you have any questions.\n\nRegards,\n{{business_name}}",
                'variables_json' => ['customer_name', 'invoice_number', 'balance_due', 'due_date', 'days_overdue', 'business_name'],
            ],
            [
                'template_key' => 'PAYMENT_RECEIVED',
                'name' => 'Payment Receipt Confirmation',
                'channel' => 'EMAIL',
                'category' => 'PAYMENTS',
                'subject' => 'Payment Received: Receipt {{receipt_number}} from {{business_name}}',
                'body_template' => "Dear {{customer_name}},\n\nThank you for your payment of {{amount_paid}} received on {{payment_date}} for Invoice {{invoice_number}}.\n\nRemaining Balance: {{balance_due}}\n\nThank you for your business,\n{{business_name}}",
                'variables_json' => ['customer_name', 'amount_paid', 'payment_date', 'invoice_number', 'receipt_number', 'balance_due', 'business_name'],
            ],
            [
                'template_key' => 'LOW_STOCK',
                'name' => 'Low Stock Warning',
                'channel' => 'IN_APP',
                'category' => 'INVENTORY',
                'subject' => 'Low Stock Alert: {{product_name}}',
                'body_template' => "Product '{{product_name}}' (SKU: {{sku}}) is below reorder level. Current Stock: {{current_stock}} (Min: {{minimum_stock}}).",
                'variables_json' => ['product_name', 'sku', 'current_stock', 'minimum_stock'],
            ],
            [
                'template_key' => 'TRANSFER_RECEIVED',
                'name' => 'Stock Transfer Received',
                'channel' => 'IN_APP',
                'category' => 'TRANSFERS',
                'subject' => 'Transfer #{{transfer_number}} Received',
                'body_template' => "Stock Transfer {{transfer_number}} with {{total_quantity}} items has been received at {{destination_warehouse}}.",
                'variables_json' => ['transfer_number', 'total_quantity', 'destination_warehouse'],
            ],
            [
                'template_key' => 'RECURRING_INVOICE_CREATED',
                'name' => 'Recurring Invoice Generated',
                'channel' => 'IN_APP',
                'category' => 'SALES',
                'subject' => 'Recurring Invoice #{{invoice_number}} Generated',
                'body_template' => "Recurring scheduled invoice {{invoice_number}} for {{customer_name}} (Total: {{invoice_total}}) was successfully generated.",
                'variables_json' => ['invoice_number', 'customer_name', 'invoice_total'],
            ],
        ];
    }
}
