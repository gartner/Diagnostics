<?php
namespace ZFToolTest\Diagnostics\TestAsset;

use Laminas\Diagnostics\Result\Success;
use Laminas\Diagnostics\Check\AbstractCheck;
use Laminas\Diagnostics\Check\CheckInterface;

class AlwaysSuccessCheck extends AbstractCheck implements CheckInterface
{
    protected $label = 'Always Successful Check';

    public function check()
    {
        return new Success();
    }
}
