<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Brand & navigation
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'short_name'     => 'Seven S',
        'product_short'  => 'Automotive',
        'aria_home'      => 'Seven S Automotive — go to homepage',
    ],

    'a11y' => [
        'skip_to_content' => 'Skip to main content',
        'breadcrumb'      => 'Breadcrumb',
    ],

    'lang' => [
        'switch_aria' => 'Switch language',
    ],

    'languages' => [
        'en' => 'English',
        'ar' => 'Arabic',
    ],

    'nav' => [
        'home'                       => 'Home',
        'products'                   => 'Products',
        'product_workshop_title'     => 'Workshop Management',
        'product_workshop_desc'      => 'Job cards, vehicles, technicians, invoices.',
        'product_spare_parts_title'  => 'Spare Parts Inventory',
        'product_spare_parts_desc'   => 'Stock control, SKUs, suppliers, multi-branch.',
        'product_accounting_title'   => 'Automotive Accounting',
        'product_accounting_desc'    => 'Invoices, VAT, payments, profit reports.',
        'pricing'                    => 'Pricing',
        'security'                   => 'Security',
        'contact'                    => 'Contact',
        'privacy'                    => 'Privacy',
        'terms'                      => 'Terms',
        'aria_primary'               => 'Primary navigation',
        'toggle_menu'                => 'Toggle menu',
    ],

    'cta' => [
        'start_trial'   => 'Start Free Trial',
        'book_demo'     => 'Book a Demo',
        'contact_sales' => 'Contact Sales',
        'learn_more'    => 'Learn more',
        'see_pricing'   => 'See pricing',
    ],

    'cta_section' => [
        'global' => [
            'title' => 'Ready to digitize your automotive business?',
            'body'  => 'Run your workshop, parts, and accounting from one cloud platform — built for the Gulf and the Middle East.',
        ],
        'product' => [
            'title' => 'Try Seven S Automotive on your own data.',
            'body'  => 'Start a free trial or book a demo with our team. Setup is fast, support is bilingual.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer
    |--------------------------------------------------------------------------
    */
    'footer' => [
        'tagline'          => 'Cloud business systems for automotive workshops, service centers, and spare-parts businesses across the Gulf and Middle East.',
        'contact_email'    => 'Email',
        'products_heading' => 'Products',
        'company_heading'  => 'Company',
        'get_started_heading' => 'Get Started',
        'legal_heading'    => 'Legal',
        'all_products'     => 'All products',
        'rights_reserved'  => 'All rights reserved.',
        'address'          => 'Headquartered in the United Arab Emirates.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Home page
    |--------------------------------------------------------------------------
    */
    'home' => [
        'meta_title'       => 'Automotive Business Management Software for Workshops, Service Centers & Spare Parts',
        'meta_description' => 'Seven S Automotive is a cloud platform that helps workshops, service centers, and spare-parts businesses manage operations, inventory, invoicing, and accounting in one place.',
        'eyebrow'          => 'Cloud platform for the automotive sector',
        'h1_a'             => 'Automotive Business Management Software',
        'h1_b'             => 'for Workshops, Service Centers & Spare Parts',
        'lead'             => 'Manage operations, inventory, invoicing, and accounting from one platform — built for the Gulf and the Middle East, in Arabic and English.',
        'trust_no_card'    => 'No credit card required',
        'trust_arabic'     => 'Full Arabic interface',
        'trust_branches'   => 'Multi-branch ready',
        'overview_kicker'  => 'Why Seven S Automotive',
        'overview_title'   => 'One cloud platform for the entire automotive workflow',
        'overview_subtitle'=> 'Stop juggling spreadsheets, paper invoices, and disconnected tools. Run the whole shop from a single, real-time system.',
        'overview_b1_t'    => 'Built for automotive',
        'overview_b1_d'    => 'Designed for workshops, garages, service centers, and parts traders — not generic ERPs.',
        'overview_b2_t'    => 'Bilingual end-to-end',
        'overview_b2_d'    => 'Arabic and English UI, RTL/LTR support, bilingual invoices, customer documents, and reports.',
        'overview_b3_t'    => 'Multi-branch ready',
        'overview_b3_d'    => 'Run a single shop today and scale to multiple branches without changing systems.',
        'overview_b4_t'    => 'Cloud and secure',
        'overview_b4_d'    => 'Hosted on the cloud with role-based access, activity logs, and tenant data isolation.',

        'systems_kicker'   => 'Three integrated systems',
        'systems_title'    => 'Workshop, Spare Parts & Accounting — under one roof',
        'systems_subtitle' => 'Pick the modules you need today and add the rest as you grow.',

        'audience_kicker'  => 'Who it’s for',
        'audience_title'   => 'Built for automotive businesses across the region',
        'audience_subtitle'=> 'Real workshops, service centers, and spare-parts shops use Seven S Automotive to organize daily operations.',
        'audience_workshops_t' => 'Auto repair workshops',
        'audience_workshops_d' => 'Job cards, technicians, parts, invoices, and warranty in one screen.',
        'audience_centers_t'   => 'Car service centers',
        'audience_centers_d'   => 'Multi-bay scheduling, branch operations, customer tracking, and reports.',
        'audience_parts_t'     => 'Spare-parts businesses',
        'audience_parts_d'     => 'Inventory control, SKUs, suppliers, multi-branch stock, and sales.',

        'benefits_kicker'   => 'Operational benefits',
        'benefits_title'    => 'Less paper. Less chasing. More visibility.',
        'benefits_subtitle' => 'A modern automotive business runs on real data — not on paper job cards and WhatsApp screenshots.',
        'benefits_b1'       => 'Track every vehicle from check-in to delivery',
        'benefits_b2'       => 'Issue Arabic/English invoices and receipts in seconds',
        'benefits_b3'       => 'Know which technician is busy, free, or behind schedule',
        'benefits_b4'       => 'See profit per job, per branch, and per service',
        'benefits_b5'       => 'Stop running out of fast-moving parts',
        'benefits_b6'       => 'Keep an audit trail of every action your team takes',

        'workflow_kicker'   => 'Workflow at a glance',
        'workflow_title'    => 'From check-in to delivery, end to end',
        'workflow_subtitle' => 'A clean workflow your team can follow without retraining every week.',
        'workflow_steps' => [
            ['t' => 'Check-in', 'd' => 'Capture customer, vehicle, complaint, photos, signatures, expected delivery.'],
            ['t' => 'Inspect & estimate', 'd' => 'Run inspections, build estimates, get customer approval.'],
            ['t' => 'Workshop & parts', 'd' => 'Assign technicians, track jobs, issue parts from inventory.'],
            ['t' => 'Invoice & deliver', 'd' => 'Issue invoice, collect payment, log warranty, hand over the vehicle.'],
        ],

        'pricing_kicker'    => 'Simple, transparent pricing',
        'pricing_title'     => 'Plans that grow with your business',
        'pricing_subtitle'  => 'Start small. Add modules and branches as you expand.',

        'security_kicker'   => 'Trust & security',
        'security_title'    => 'Your business data, protected by design',
        'security_subtitle' => 'Cloud-grade security, role-based access, full audit logs, and isolated tenant data.',
        'security_b1'       => 'Encrypted in transit',
        'security_b2'       => 'Role-based permissions',
        'security_b3'       => 'Tenant data isolation',
        'security_b4'       => 'Activity & audit logs',

        'faq_kicker'   => 'Common questions',
        'faq_title'    => 'Frequently asked questions',
        'faq' => [
            [
                'q' => 'Is Seven S Automotive a cloud platform?',
                'a' => 'Yes. The platform runs entirely in the cloud, so your team can access it from any computer or mobile browser.',
            ],
            [
                'q' => 'Does it support Arabic and English?',
                'a' => 'Yes. The full interface, customer documents, and reports are available in Arabic and English. RTL and LTR are both fully supported.',
            ],
            [
                'q' => 'Can I run multiple branches?',
                'a' => 'Yes. The platform is multi-branch from day one. You can manage branch staff, branch inventory, and branch reports separately.',
            ],
            [
                'q' => 'Do I need to install any software?',
                'a' => 'No. Seven S Automotive runs in the browser. There is nothing to install on workstations.',
            ],
            [
                'q' => 'Can I start with workshop management only and add accounting later?',
                'a' => 'Yes. Each system can be enabled separately. Add Spare Parts Inventory and Automotive Accounting whenever you are ready.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Products index
    |--------------------------------------------------------------------------
    */
    'products_index' => [
        'meta_title'       => 'Products — Workshop, Spare Parts & Automotive Accounting',
        'meta_description' => 'Explore the three core systems of Seven S Automotive: Workshop Management, Spare Parts Inventory Management, and Automotive Accounting.',
        'h1'               => 'Three integrated systems for automotive businesses',
        'lead'             => 'Pick what you need today. Add the others as you grow.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product 1: Workshop Management
    |--------------------------------------------------------------------------
    */
    'product_workshop' => [
        'meta_title'       => 'Workshop Management Software for Garages and Service Centers',
        'meta_description' => 'Run your auto repair shop or service center from one cloud platform. Job cards, vehicle history, customers, technicians, invoices, and multi-branch reports.',
        'crumb'            => 'Workshop Management',
        'eyebrow'          => 'Workshop Management Software',
        'h1'               => 'Workshop Management Software for Garages and Service Centers',
        'lead'             => 'Replace paper job cards, scattered notebooks, and disconnected spreadsheets with one cloud platform built for auto workshops in the Gulf and the Middle East.',

        'problem_title'    => 'Manual workshop operations cost real money',
        'problem_body'     => 'Lost job cards, forgotten parts, untracked technician time, and customer follow-ups falling through the cracks — these are the everyday symptoms of running a workshop on paper.',
        'problem_b1'       => 'Lost or unclear job cards',
        'problem_b2'       => 'No reliable vehicle history',
        'problem_b3'       => 'Manual invoicing and missed line items',
        'problem_b4'       => 'No visibility into technician productivity',

        'solution_title'   => 'A purpose-built workshop system, end to end',
        'solution_body'    => 'Seven S Automotive replaces fragmented tools with one platform: customer, vehicle, job card, parts, invoice, payment, warranty, and reports — all connected.',

        'features' => [
            'jobcard'   => ['t' => 'Digital job cards', 'd' => 'Capture complaint, inspection, services, parts, and signatures from the moment the vehicle arrives.'],
            'history'   => ['t' => 'Vehicle 360 & history', 'd' => 'See every visit, repair, part, and invoice for each VIN — no more searching old folders.'],
            'customers' => ['t' => 'Customer management', 'd' => 'Customer 360 view with contact, vehicles, balance, history, approvals, and follow-ups.'],
            'tech'      => ['t' => 'Technician tasks', 'd' => 'Assign jobs, track time, capture before/after photos, and see who is busy in real time.'],
            'invoice'   => ['t' => 'Invoices & payments', 'd' => 'Bilingual invoices, partial payments, customer balance, and printable PDFs.'],
            'reports'   => ['t' => 'Operational reports', 'd' => 'Revenue, profit, technician productivity, branch performance, and lost-sale analysis.'],
            'multi'     => ['t' => 'Multi-branch support', 'd' => 'Manage one workshop or multiple branches with separate users, inventory, and reports.'],
            'lang'      => ['t' => 'Arabic & English UI', 'd' => 'Full RTL Arabic UI and LTR English UI, with bilingual customer-facing documents.'],
        ],

        'faq' => [
            ['q' => 'Can I customize the job card fields?', 'a' => 'Yes. You can adjust check-in fields, inspection templates, and complaint types to match how your shop works.'],
            ['q' => 'Does it handle warranty and comebacks?', 'a' => 'Yes. The platform tracks warranty terms, comebacks, and rework so issues do not get lost.'],
            ['q' => 'Can I print bilingual invoices?', 'a' => 'Yes. Customer documents are produced in Arabic and English with proper RTL/LTR rendering.'],
            ['q' => 'Can technicians log time?', 'a' => 'Yes. Technicians can be assigned to jobs and time can be tracked per task.'],
            ['q' => 'Does it integrate with the spare parts and accounting systems?', 'a' => 'Yes. Workshop integrates with Seven S Automotive Spare Parts Inventory and Automotive Accounting if you enable them.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product 2: Spare Parts Inventory
    |--------------------------------------------------------------------------
    */
    'product_spare_parts' => [
        'meta_title'       => 'Spare Parts Inventory Management Software for Auto Parts Businesses',
        'meta_description' => 'Manage spare parts stock, SKUs, suppliers, purchases, and sales across one or many branches. Built to organize inventory today and prepare for future digital growth.',
        'crumb'            => 'Spare Parts Inventory',
        'eyebrow'          => 'Spare Parts Inventory Management',
        'h1'               => 'Spare Parts Inventory Management Software for Auto Parts Businesses',
        'lead'             => 'Inventory management today. Prepared for future digital growth tomorrow. A modern stock system built for auto parts traders, workshops with parts counters, and multi-branch operations.',

        'future_message'   => 'Built to help spare parts businesses organize their stock today and prepare for future digital growth.',

        'problem_title'    => 'Disorganized stock costs you sales',
        'problem_body'     => 'When the part isn’t found, the customer leaves. When stock counts are wrong, you lose money on overstock and dead stock at the same time.',
        'problem_b1'       => 'No reliable on-hand stock per branch',
        'problem_b2'       => 'Slow part lookup by part number or SKU',
        'problem_b3'       => 'No visibility into fast-moving and dead stock',
        'problem_b4'       => 'Disconnected purchase, sales, and supplier data',

        'solution_title'   => 'A structured spare-parts inventory, ready for growth',
        'solution_body'    => 'Seven S Automotive Spare Parts Inventory organizes parts, SKUs, suppliers, purchases, and sales — and is structured so you can later plug into broader digital workflows.',

        'features' => [
            'stock'       => ['t' => 'Stock management', 'd' => 'Track on-hand quantity per branch, locations, and movement history.'],
            'sku'         => ['t' => 'SKU & part numbers', 'd' => 'Search by SKU, part number, OEM number, brand, vehicle make/model.'],
            'po'          => ['t' => 'Purchase orders', 'd' => 'Issue purchase orders to suppliers and receive stock with proper landed cost.'],
            'suppliers'   => ['t' => 'Supplier management', 'd' => 'Maintain supplier profiles, contacts, terms, and purchase history.'],
            'sales'       => ['t' => 'Sales orders & counter sales', 'd' => 'Issue sales orders, counter sales, and link parts directly to workshop jobs.'],
            'lowstock'    => ['t' => 'Low-stock alerts', 'd' => 'Get notified when fast-moving parts reach reorder levels per branch.'],
            'multibranch' => ['t' => 'Multi-branch stock', 'd' => 'Run multiple branches and warehouses with branch-level visibility.'],
            'reports'     => ['t' => 'Inventory reports', 'd' => 'Stock value, slow movers, fast movers, supplier performance, and audit trails.'],
        ],

        'future_growth_title' => 'Inventory today. Future digital growth tomorrow.',
        'future_growth_body'  => 'Your stock data is structured cleanly so it can later support broader digital catalog and supplier-network workflows when those become available.',

        'avoided_promises_note' => 'Marketplace, commission-based sales, on-behalf-of-supplier selling, delivery, and rewards are not part of this product today.',

        'faq' => [
            ['q' => 'Is this an inventory system or a marketplace?', 'a' => 'This is an inventory management system. Marketplace and supplier-network features are not part of the current product.'],
            ['q' => 'Can I run multi-branch inventory?', 'a' => 'Yes. You can manage stock per branch, transfer between branches, and view branch-level reports.'],
            ['q' => 'Can it integrate with the workshop system?', 'a' => 'Yes. Parts can be issued directly to workshop jobs and tracked in the same platform.'],
            ['q' => 'Does it handle purchase orders and supplier accounts?', 'a' => 'Yes. You can issue purchase orders, receive stock, and maintain supplier records and balances.'],
            ['q' => 'Can I import my existing parts catalog?', 'a' => 'CSV/Excel import is on the roadmap. You can also start by adding fast-moving parts manually and grow from there.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product 3: Automotive Accounting
    |--------------------------------------------------------------------------
    */
    'product_accounting' => [
        'meta_title'       => 'Automotive Accounting Software for Workshops and Spare Parts Businesses',
        'meta_description' => 'Specialized accounting for automotive businesses: invoices, receipts, expenses, supplier payments, customer balances, VAT-ready invoices, and profit reports.',
        'crumb'            => 'Automotive Accounting',
        'eyebrow'          => 'Automotive Accounting Software',
        'h1'               => 'Automotive Accounting Software for Workshops and Spare Parts Businesses',
        'lead'             => 'Accounting that understands automotive workflows: jobs, parts, suppliers, branches, and warranty. Bilingual, VAT-ready, and connected to your operational data.',

        'why_title'        => 'Why automotive businesses need specialized accounting',
        'why_body'         => 'Generic accounting software wasn’t designed for job cards, parts issued from inventory, technician work, multi-branch operations, or warranty. Seven S Automotive Accounting is.',

        'features' => [
            'invoices' => ['t' => 'Invoices', 'd' => 'Issue bilingual invoices, link them to jobs and parts, and print proper PDFs.'],
            'receipts' => ['t' => 'Receipts', 'd' => 'Record customer payments and partial payments with full audit history.'],
            'expenses' => ['t' => 'Expenses', 'd' => 'Track shop expenses by branch, category, and date for proper P&L.'],
            'suppliers'=> ['t' => 'Supplier payments', 'd' => 'Manage supplier balances, bills, and payments tied to parts purchases.'],
            'cust_bal' => ['t' => 'Customer balances', 'd' => 'See open balances, aging, and payment history per customer.'],
            'vat'      => ['t' => 'VAT-ready invoices', 'd' => 'VAT-aware invoice and tax reporting structure for the Gulf market.'],
            'profit'   => ['t' => 'Profit reports', 'd' => 'See gross profit per job, per branch, and per service category.'],
            'tracking' => ['t' => 'Payment tracking', 'd' => 'Know which invoices are paid, partial, overdue, or written off.'],
            'reports'  => ['t' => 'Accounting reports', 'd' => 'Trial balance, profit & loss, balance sheet, and statement of account.'],
            'integration'=> ['t' => 'Workshop & inventory integration', 'd' => 'Tightly connected with Workshop and Spare Parts Inventory — no double entry.'],
        ],

        'faq' => [
            ['q' => 'Is the accounting system VAT-ready?', 'a' => 'Yes. Invoices and tax reporting are designed with VAT in the Gulf in mind.'],
            ['q' => 'Does it integrate with the workshop and parts systems?', 'a' => 'Yes. Jobs, invoices, parts issued, and supplier purchases all flow into the accounting layer without re-entry.'],
            ['q' => 'Can I run accounting per branch?', 'a' => 'Yes. You can view financials per branch and consolidated across the company.'],
            ['q' => 'Can it produce financial statements?', 'a' => 'Yes. Trial balance, profit & loss, and balance sheet are part of the platform.'],
            ['q' => 'Is the accounting standalone or do I need the whole suite?', 'a' => 'You can start with what you need today. Accounting can run with or without the other modules.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'meta_title'       => 'Pricing — Plans for Workshops, Service Centers & Spare Parts Businesses',
        'meta_description' => 'Simple, transparent pricing for Seven S Automotive. Choose Starter, Professional, Business, or Enterprise — start with a free trial.',
        'crumb'            => 'Pricing',
        'eyebrow'          => 'Pricing',
        'h1'               => 'Plans built for automotive businesses of every size',
        'lead'             => 'Start with a free trial. Upgrade when you’re ready. No hidden fees.',
        'per_month'        => 'per month',
        'custom_price'     => 'Custom',
        'contact_for_quote'=> 'Contact us for a quote',
        'badge_popular'    => 'Most popular',
        'plans' => [
            'starter' => [
                'name'    => 'Starter',
                'tagline' => 'For small workshops getting started.',
            ],
            'professional' => [
                'name'    => 'Professional',
                'tagline' => 'For growing workshops and service centers.',
            ],
            'business' => [
                'name'    => 'Business',
                'tagline' => 'For businesses using all three systems.',
            ],
            'enterprise' => [
                'name'    => 'Enterprise',
                'tagline' => 'For larger multi-branch operations.',
            ],
        ],
        'features' => [
            '1_branch'              => '1 branch',
            'basic_users'           => 'Basic user accounts',
            'job_cards'             => 'Digital job cards',
            'invoices'              => 'Invoices & receipts',
            'customer_records'      => 'Customer records',
            'basic_reports'         => 'Basic reports',
            'multi_users'           => 'More users',
            'inventory_basics'      => 'Inventory basics',
            'customer_history'      => 'Customer & vehicle history',
            'advanced_reports'      => 'Advanced reports',
            'payments'              => 'Payments & balances',
            'multilingual_ui'       => 'Multilingual interface',
            'workshop_full'         => 'Full workshop management',
            'spare_parts'           => 'Spare parts inventory',
            'accounting'            => 'Automotive accounting',
            'multi_branch'          => 'Multi-branch operations',
            'supplier_management'   => 'Supplier management',
            'custom_branches'       => 'Custom branch limits',
            'advanced_permissions'  => 'Advanced permissions',
            'dedicated_onboarding'  => 'Dedicated onboarding',
            'custom_support'        => 'Custom support',
            'future_integrations'   => 'Future integrations',
        ],
        'faq_kicker' => 'Pricing questions',
        'faq_title'  => 'Pricing FAQ',
        'faq' => [
            ['q' => 'Is there a free trial?', 'a' => 'Yes. You can start a free trial and explore the platform before subscribing.'],
            ['q' => 'Can I change plans later?', 'a' => 'Yes. You can upgrade or downgrade as your business grows or changes.'],
            ['q' => 'Are prices final?', 'a' => 'Prices shown are starting points. For multi-branch and Enterprise needs, contact our sales team for a tailored quote.'],
            ['q' => 'Which payment methods are supported?', 'a' => 'We support standard online card payments through a secure provider. Local invoicing is also available for Enterprise.'],
            ['q' => 'Do I need to commit long-term?', 'a' => 'No long-term commitment is required for the standard plans.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead pages — Book Demo
    |--------------------------------------------------------------------------
    */
    'book_demo' => [
        'meta_title'       => 'Book a Demo — Seven S Automotive',
        'meta_description' => 'Book a one-on-one demo with the Seven S Automotive team. We will show you how the platform fits your workshop, service center, or parts business.',
        'crumb'            => 'Book a Demo',
        'h1'               => 'Book a personal demo',
        'lead'             => 'Tell us about your business and we will tailor the demo to your workflow.',
    ],

    'start_trial' => [
        'meta_title'       => 'Start Free Trial — Seven S Automotive',
        'meta_description' => 'Start your free trial of Seven S Automotive. Manage workshop, parts, and accounting in one cloud platform — in Arabic and English.',
        'crumb'            => 'Start Free Trial',
        'h1'               => 'Start your free trial',
        'lead'             => 'Tell us a few details and our team will get your trial workspace ready.',
    ],

    'contact' => [
        'meta_title'       => 'Contact Sales — Seven S Automotive',
        'meta_description' => 'Talk to the Seven S Automotive sales team about pricing, multi-branch deployments, and Enterprise needs.',
        'crumb'            => 'Contact',
        'h1'               => 'Contact our sales team',
        'lead'             => 'Whether you’re evaluating, scaling, or need an Enterprise quote — we’re here to help.',
        'side_email'       => 'Email',
        'side_email_value' => 'sales@seven-scapital.com',
        'side_response'    => 'We respond within one business day.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */
    'form' => [
        'full_name'           => 'Full name',
        'company_name'        => 'Company name',
        'business_type'       => 'Business type',
        'country'             => 'Country',
        'phone'               => 'Phone / WhatsApp',
        'email'               => 'Email',
        'branches_count'      => 'Number of branches',
        'interested_system'   => 'Interested system',
        'preferred_language'  => 'Preferred language',
        'message'             => 'Message',
        'select_placeholder'  => 'Select...',
        'submit_book_demo'    => 'Book my demo',
        'submit_start_trial'  => 'Start my free trial',
        'submit_contact'      => 'Send message',
        'errors_heading'      => 'Please fix the following:',
        'privacy_note'        => 'By submitting this form you agree to our Privacy Policy.',
        'honeypot_label'      => 'Leave this field empty',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead form options
    |--------------------------------------------------------------------------
    */
    'business_types' => [
        'auto_repair_workshop' => 'Auto repair workshop',
        'car_service_center'   => 'Car service center',
        'spare_parts_business' => 'Spare parts business',
        'other_automotive'     => 'Other automotive business',
    ],

    'interested_systems' => [
        'workshop_management'   => 'Workshop Management',
        'spare_parts_inventory' => 'Spare Parts Inventory',
        'automotive_accounting' => 'Automotive Accounting',
        'full_suite'            => 'Full Automotive Suite',
    ],

    'countries' => [
        'AE'    => 'United Arab Emirates',
        'SA'    => 'Saudi Arabia',
        'KW'    => 'Kuwait',
        'QA'    => 'Qatar',
        'BH'    => 'Bahrain',
        'OM'    => 'Oman',
        'JO'    => 'Jordan',
        'EG'    => 'Egypt',
        'LB'    => 'Lebanon',
        'IQ'    => 'Iraq',
        'MA'    => 'Morocco',
        'TN'    => 'Tunisia',
        'DZ'    => 'Algeria',
        'LY'    => 'Libya',
        'YE'    => 'Yemen',
        'PS'    => 'Palestine',
        'SD'    => 'Sudan',
        'OTHER' => 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Thank-you pages
    |--------------------------------------------------------------------------
    */
    'thank_you' => [
        'demo' => [
            'meta_title'       => 'Demo request received — Seven S Automotive',
            'meta_description' => 'Thanks for requesting a demo. Our team will reach out shortly.',
            'h1'               => 'Demo request received',
            'body'             => 'Thank you. Our team will contact you within one business day to schedule your demo.',
        ],
        'trial' => [
            'meta_title'       => 'Trial request received — Seven S Automotive',
            'meta_description' => 'Thanks for starting a free trial. Our team will set up your workspace.',
            'h1'               => 'Trial request received',
            'body'             => 'Thank you. Our team will reach out to set up your trial workspace.',
        ],
        'contact' => [
            'meta_title'       => 'Message received — Seven S Automotive',
            'meta_description' => 'Thanks for getting in touch. We will respond within one business day.',
            'h1'               => 'Message received',
            'body'             => 'Thank you. We will get back to you within one business day.',
        ],
        'next_step'        => 'In the meantime, explore the platform:',
        'next_link_pricing'=> 'See pricing',
        'next_link_workshop' => 'Workshop Management',
        'next_link_parts'  => 'Spare Parts Inventory',
        'next_link_accounting' => 'Automotive Accounting',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security page
    |--------------------------------------------------------------------------
    */
    'security' => [
        'meta_title'       => 'Security — Seven S Automotive',
        'meta_description' => 'How Seven S Automotive protects your business data: cloud security, role-based permissions, tenant data isolation, secure logins, backups, and audit logs.',
        'crumb'            => 'Security',
        'eyebrow'          => 'Security & Trust',
        'h1'               => 'Your business data, protected by design',
        'lead'             => 'Seven S Automotive treats your data as critical infrastructure for your business.',
        'sections' => [
            ['t' => 'Cloud platform security', 'd' => 'The platform runs on a managed cloud infrastructure with industry-standard practices for hosting, encryption in transit, and patching.'],
            ['t' => 'User roles and permissions', 'd' => 'Granular role-based access control. Owners, managers, technicians, advisors, and accountants get only what they need.'],
            ['t' => 'Tenant data isolation', 'd' => 'Each tenant workspace runs with isolated data. Your business data is never mixed with other tenants.'],
            ['t' => 'Secure login', 'd' => 'Email/password authentication, password reset flows, and secure session handling.'],
            ['t' => 'Backups', 'd' => 'Database backup procedures are part of the standard operations process.'],
            ['t' => 'Payment security', 'd' => 'Online payments are processed through a trusted payment provider. We do not store raw card data.'],
            ['t' => 'Activity logs', 'd' => 'Critical actions are logged for audit, traceability, and accountability.'],
            ['t' => 'Business data protection', 'd' => 'Your customer, vehicle, invoice, and accounting data belongs to you and is exportable.'],
        ],
    ],

    'privacy' => [
        'meta_title'       => 'Privacy Policy — Seven S Automotive',
        'meta_description' => 'How Seven S Automotive collects, uses, and protects your personal and business information.',
        'crumb'            => 'Privacy Policy',
        'h1'               => 'Privacy Policy',
        'last_updated'     => 'Last updated',
        'last_updated_value' => '2026-05-10',
        'sections' => [
            ['t' => 'Introduction', 'd' => 'This Privacy Policy explains how Seven S Capital (“we”, “us”) collects, uses, and protects information when you use the Seven S Automotive website and platform.'],
            ['t' => 'Information we collect', 'd' => 'We collect contact details you submit through forms (name, email, phone, company), basic technical information (IP, user agent), and platform usage data needed to operate the service.'],
            ['t' => 'How we use information', 'd' => 'We use information to provide the platform, respond to inquiries, fulfill subscriptions, send service messages, and comply with legal obligations.'],
            ['t' => 'Sharing of information', 'd' => 'We do not sell your personal information. We may share information with trusted processors (hosting, payments) strictly as needed to operate the service.'],
            ['t' => 'Data retention', 'd' => 'We retain information as long as needed to provide the service or as required by law.'],
            ['t' => 'Your rights', 'd' => 'You may contact us to access, correct, or delete personal data, subject to applicable laws.'],
            ['t' => 'Contact', 'd' => 'For privacy questions contact info@seven-scapital.com.'],
        ],
    ],

    'terms' => [
        'meta_title'       => 'Terms of Service — Seven S Automotive',
        'meta_description' => 'The terms and conditions that govern the use of Seven S Automotive.',
        'crumb'            => 'Terms of Service',
        'h1'               => 'Terms of Service',
        'last_updated'     => 'Last updated',
        'last_updated_value' => '2026-05-10',
        'sections' => [
            ['t' => 'Acceptance of terms', 'd' => 'By accessing or using Seven S Automotive you agree to these Terms of Service.'],
            ['t' => 'Use of the service', 'd' => 'You agree to use the platform lawfully, not to abuse the service, not to attempt unauthorized access, and to respect the rights of other users.'],
            ['t' => 'Subscriptions and billing', 'd' => 'Paid plans are billed according to the selected plan. Pricing on the marketing site is informational and may be adjusted.'],
            ['t' => 'Customer data', 'd' => 'Your business data remains yours. We act as a data processor on your behalf to provide the service.'],
            ['t' => 'Service availability', 'd' => 'We work to maintain high availability but do not guarantee uninterrupted access. Maintenance windows may occur.'],
            ['t' => 'Termination', 'd' => 'You may cancel your subscription at any time. We may suspend service for breach of these terms.'],
            ['t' => 'Limitation of liability', 'd' => 'To the maximum extent allowed by law, Seven S Capital is not liable for indirect, incidental, or consequential damages.'],
            ['t' => 'Governing law', 'd' => 'These terms are governed by the laws of the United Arab Emirates unless agreed otherwise.'],
            ['t' => 'Contact', 'd' => 'For questions about these terms contact info@seven-scapital.com.'],
        ],
    ],
];
