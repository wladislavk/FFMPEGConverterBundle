<?php
namespace VKR\FFMPEGConverterBundle\Services;

use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use VKR\CustomLoggerBundle\Services\CustomLogger;
use VKR\FFMPEGConverterBundle\Decorators\ShellDecorator;

class Converter
{
    /**
     * @var string
     */
    private $ffmpegPath;

    /**
     * @var CustomLogger
     */
    private $customLogger;

    /**
     * @var ShellDecorator
     */
    private $shellDecorator;

    /**
     * @var array
     */
    private $converterParams;

    public function __construct(
        CustomLogger $customLogger,
        ShellDecorator $shellDecorator,
        $ffmpegPath,
        array $converterParams
    ) {
        $this->customLogger = $customLogger;
        $this->shellDecorator = $shellDecorator;
        $this->ffmpegPath = $ffmpegPath;
        $this->converterParams = $converterParams;
    }

    /**
     * @param File $file
     * @param string $destinationDirectory
     * @param string $logFile
     * @param int $length
     * @return File
     * @throws \Exception
     */
    public function convertFile(File $file, $destinationDirectory, $logFile, $length=0)
    {
        $baseType = $this->getBaseFileType($file);
        $logger = $this->customLogger->setLogger($logFile);
        $destinationPath = $destinationDirectory . $this->changeExtension($file->getFilename(), $baseType);
        switch ($baseType) {
            case 'video':
                $params = $this->getVideoParams();
                break;
            case 'audio':
                $params = $this->getAudioParams();
                break;
            case 'image':
                $params = $this->getImageParams($length);
                break;
            default:
                throw new FileException('The file is not a multimedia file');
        }
        if (!isset($params['input']) || !isset($params['output'])) {
            throw new InvalidConfigurationException(
                "Configuration for type $baseType is undefined or malformed"
            );
        }
        $this->callFFMPEG($file->getRealPath(), $destinationPath, $logger, $params);
        if (!is_file($destinationPath)) {
            throw new FileException("File $destinationPath was not converted, see $logFile.log for errors");
        }
        return new File($destinationPath);
    }

    /**
     * @param string $filename
     * @param string $baseType
     * @return string
     * @throws InvalidConfigurationException
     */
    private function changeExtension($filename, $baseType)
    {
        if (!isset($this->converterParams[$baseType]['extension'])) {
            throw new InvalidConfigurationException(
                "Configuration for type $baseType is undefined or malformed"
            );
        }
        $extension = $this->converterParams[$baseType]['extension'];
        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        return $filenameWithoutExtension . '.' . $extension;
    }

    /**
     * @param File $file
     * @throws FileException|FileNotFoundException
     */
    private function getBaseFileType(File $file)
    {
        $fileType = $file->getMimeType();
        $baseType = explode('/', $fileType)[0];
        return $baseType;
    }

    /**
     * @return array
     */
    private function getVideoParams()
    {
        $params = $this->converterParams['video'];
        return $params;
    }

    /**
     * @return array
     */
    private function getAudioParams()
    {
        $params = $this->converterParams['audio'];
        return $params;
    }

    /**
     * @param int $length
     * @return array
     */
    private function getImageParams($length)
    {
        $params = $this->converterParams['image'];
        if ($length) {
            $params['output'] .= " -t $length";
        }
        return $params;
    }

    /**
     * @param string $source
     * @param string $destination
     * @param Logger $logger
     * @param array $params
     * @throws \RuntimeException
     */
    private function callFFMPEG($source, $destination, Logger $logger, array $params=[])
    {
        $command = $this->ffmpegPath . ' ';
        $command .= '-loglevel quiet ';
        $command .= $params['input'] . ' ';
        $command .= "-i $source ";
        $command .= $params['output'] . ' ';
        $command .= $destination;
        // unit-testable version of calling exec()
        $this->shellDecorator->exec($command, $commandOutput, $returnCode);
        $outputString = implode(' ', $commandOutput);

        // PHP manual says that $returnCode should be string
        if ($returnCode !== 0 && $returnCode !== '0') {
            $logger->addInfo("Error while running FFMPEG. Command: $command. Output: $outputString");
            throw new \RuntimeException("FFMPEG error: $outputString");
        }
        $logger->addInfo("Converted $source to $destination. Command: $command. Output: $outputString");
    }
}
