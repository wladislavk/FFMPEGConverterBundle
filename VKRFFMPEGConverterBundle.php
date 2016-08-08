<?php
namespace VKR\FFMPEGConverterBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use VKR\FFMPEGConverterBundle\DependencyInjection\VKRFFMPEGConverterExtension;

class VKRFFMPEGConverterBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new VKRFFMPEGConverterExtension();
    }
}
