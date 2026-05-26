<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Deployer;

host('forger')
    ->setHostname(getenv('SSH_HOST'))
    ->setRemoteUser(getenv('SSH_USER'))
    ->setPort(getenv('SSH_PORT', 22))
    ->setDeployPath(getenv('SSH_PATH', '/var/www/deployer'));

// host does not allow GHA whitelisting so we use a jump box (ssh config built in GHA)
host('forger-jump')
    ->setHostname('forger-jump')
    ->setRemoteUser(getenv('SSH_USER'))
    ->setDeployPath(getenv('SSH_PATH', '/var/www/deployer'));
