<?php

return [
    /*
    | Permite URLs privadas/localhost na validação SSRF (somente dev/homologação).
    */
    'ssrf_allow_private' => env('INTEGRATION_SSRF_ALLOW_PRIVATE', false),

    /*
    | Hosts bloqueados mesmo em desenvolvimento.
    */
    'ssrf_blocked_hosts' => [
        '0.0.0.0',
    ],
];
