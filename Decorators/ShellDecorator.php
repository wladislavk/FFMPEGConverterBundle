<?php
namespace VKR\FFMPEGConverterBundle\Decorators;

class ShellDecorator
{
    /**
     * @param string $command
     * @param array|null $output
     * @param int|null $return_var
     * @return string
     */
    public function exec($command, array &$output = null, &$return_var = null)
    {
        return exec($command, $output, $return_var);
    }
}
