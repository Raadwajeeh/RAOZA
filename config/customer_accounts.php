<?php

return [
    'guest_order_link_minutes' => max(5, min(1440, (int) env('GUEST_ORDER_LINK_MINUTES', 30))),
];
