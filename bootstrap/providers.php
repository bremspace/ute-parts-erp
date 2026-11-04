<?php

use App\Modules\Akunting\Providers\AkuntingServiceProvider;
use App\Modules\Crm\Providers\CrmServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Hr\Providers\HrServiceProvider;
use App\Modules\Marketplace\Providers\MarketplaceServiceProvider;
use App\Modules\Notifikasi\Providers\NotifikasiServiceProvider;
use App\Modules\Omnichannel\Providers\OmnichannelServiceProvider;
use App\Modules\Pos\Providers\PosServiceProvider;
use App\Modules\Rbac\Providers\RbacServiceProvider;
use App\Modules\Report\Providers\ReportServiceProvider;
use App\Modules\Reseller\Providers\ResellerServiceProvider;
use App\Modules\Servis\Providers\ServisServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Modules\Wms\Providers\WmsServiceProvider;
use App\Modules\Workflow\Providers\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AkuntingServiceProvider::class,
    CrmServiceProvider::class,
    DashboardServiceProvider::class,
    HrServiceProvider::class,
    MarketplaceServiceProvider::class,
    NotifikasiServiceProvider::class,
    OmnichannelServiceProvider::class,
    PosServiceProvider::class,
    RbacServiceProvider::class,
    ReportServiceProvider::class,
    ResellerServiceProvider::class,
    ServisServiceProvider::class,
    WebhookServiceProvider::class,
    WmsServiceProvider::class,
    WorkflowServiceProvider::class,
];
