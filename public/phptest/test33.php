<?php

/*
 * Read-only comparison: Pantheon-created vs eNalog-prepared/created
 * prijem VP skladište (document type 6100).
 *
 * Browser: /phptest/test33.php?pantheon_rn=26-6000-003429&enalog_rn=26-6000-003059
 * CLI: php public/phptest/test33.php "pantheon_rn=26-6000-003429&enalog_rn=26-6000-003059"
 */

declare(strict_types=1);

const PHPTEST_CLOSE_KIND = 'receipt';
const PHPTEST_CLOSE_DOC_TYPE = '6100';
const PHPTEST_CLOSE_TITLE = 'Prijem VP skladište: Pantheon vs eNalog';

require __DIR__ . '/_work_order_close_compare.php';

