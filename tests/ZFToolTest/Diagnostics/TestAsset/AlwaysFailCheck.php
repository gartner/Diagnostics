<?php
namespace ZFToolTest\Diagnostics\TestAsset;

use Laminas\Diagnostics\Check\AbstractCheck;
use Laminas\Diagnostics\Check\CheckInterface;
use Laminas\Diagnostics\Result\Failure;

class AlwaysSuccessCheck extends AbstractCheck implements CheckInterface
{
    protected $label = 'Always Fail Check';

    public function check()
    {
        return new Failure();
    }
}
