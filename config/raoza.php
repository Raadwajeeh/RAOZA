<?php
return [
 'brand' => ['name'=>'RAOZA','domain'=>'raoza.nl','market'=>'NL','currency'=>'EUR'],
 'colors' => ['primary'=>'#330313','secondary'=>'#602032','cream'=>'#FFF6E6','gold'=>'#D1AF6F','black'=>'#111111'],
 'typography' => ['display'=>'Playfair Display','ui'=>'Plus Jakarta Sans'],
 'consent' => ['policy_version'=>env('CONSENT_POLICY_VERSION','2026-09-v1')],
 'analytics' => ['provider'=>env('ANALYTICS_PROVIDER','none')],
];
