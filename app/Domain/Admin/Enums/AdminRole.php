<?php
namespace App\Domain\Admin\Enums;

enum AdminRole:string
{
    case Customer='customer'; case Owner='owner'; case Admin='admin'; case OrderManager='order_manager'; case Fulfillment='fulfillment'; case Support='support';

    public function isStaff():bool{return $this!==self::Customer;}

    public function permissions():array{return match($this){
        self::Owner=>['*'],
        self::Admin=>['dashboard.view','products.view','products.manage','inventory.view','inventory.adjust','orders.view','orders.manage','fulfillment.manage','returns.view','returns.manage','refunds.manage','discounts.manage','content.manage','customers.view'],
        self::OrderManager=>['dashboard.view','orders.view','orders.manage','returns.view','returns.manage','refunds.manage','customers.view'],
        self::Fulfillment=>['dashboard.view','orders.view','fulfillment.manage','inventory.view'],
        self::Support=>['dashboard.view','orders.view','returns.view','customers.view'],
        default=>[]
    };}

    public function allows(string $permission):bool{return in_array('*',$this->permissions(),true)||in_array($permission,$this->permissions(),true);}
}
