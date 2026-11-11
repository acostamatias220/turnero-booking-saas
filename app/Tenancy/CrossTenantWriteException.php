<?php

declare(strict_types=1);

namespace App\Tenancy;

use LogicException;

final class CrossTenantWriteException extends LogicException {}
