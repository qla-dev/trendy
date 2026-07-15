<?php

/*
 * Read-only comparison: Pantheon-created vs eNalog-prepared/created
 * razdužene operacije (document type 6600).
 *
 * Browser: /phptest/test32.php?pantheon_rn=26-6000-003429&enalog_rn=26-6000-003059
 * CLI: php public/phptest/test32.php "pantheon_rn=26-6000-003429&enalog_rn=26-6000-003059"
 */

declare(strict_types=1);

const PHPTEST_CLOSE_KIND = 'operations';
const PHPTEST_CLOSE_DOC_TYPE = '6600';
const PHPTEST_CLOSE_TITLE = 'Razdužene operacije: Pantheon vs eNalog';

require __DIR__ . '/_work_order_close_compare.php';

