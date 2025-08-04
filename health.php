<?php
declare(strict_types=1);
// Einfache Statusseite für Craft CMS

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo "Craft CMS Health Check\n";
echo "=====================\n";