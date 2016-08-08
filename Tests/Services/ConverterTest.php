<?php
namespace VKR\FFMPEGConverterBundle\Tests\Services;

use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use VKR\CustomLoggerBundle\Services\CustomLogger;
use VKR\FFMPEGConverterBundle\Decorators\ShellDecorator;
use VKR\FFMPEGConverterBundle\Services\Converter;
use VKR\SettingsBundle\Services\SettingsRetriever;

class ConverterTest extends \PHPUnit_Framework_TestCase
{
    const SOURCE_FILE = __DIR__ . '/../../TestHelpers/static/source/test.csv';
    const DESTINATION_DIR = __DIR__ . '/../../TestHelpers/static/destination/';

    const FFMPEG_PATH = '/usr/bin/ffmpeg';

    protected $converterParams = [
        'video' => [
            'extension' => 'txt',
            'input' => 'video_source_params',
            'output' => 'video_destination_params',
        ],
        'image' => [
            'extension' => 'txt',
            'input' => 'image_source_params',
            'output' => 'image_destination_params',
        ],
        'text' => [
            'extension' => 'txt',
            'input' => 'image_source_params',
            'output' => 'image_destination_params',
        ],
    ];

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $customLogger;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $shellDecorator;

    /**
     * @var string
     */
    protected $loggedOutput;

    /**
     * @var array
     */
    protected $fileParams;

    public function setUp()
    {
        $this->mockLogger();
        $this->mockCustomLogger();
    }

    public function testVideoFormat()
    {
        $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file');
        $expectedCommand =
            '/usr/bin/ffmpeg video_source_params -i ' . self::SOURCE_FILE . ' video_destination_params ' . self::DESTINATION_DIR . 'test.txt';
        $expectedLog =
            "Converted " . self::SOURCE_FILE . " to " . self::DESTINATION_DIR . "test.txt. Command: $expectedCommand. Output: All is OK";
        $this->assertEquals($expectedLog, $this->loggedOutput);
    }

    public function testImageFormatWithLength()
    {
        $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'image/jpg',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
        $expectedCommand =
            '/usr/bin/ffmpeg image_source_params -i ' . self::SOURCE_FILE . ' image_destination_params -t 30 ' . self::DESTINATION_DIR . 'test.txt';
        $expectedLog =
            "Converted " . self::SOURCE_FILE . " to " . self::DESTINATION_DIR . "test.txt. Command: $expectedCommand. Output: All is OK";
        $this->assertEquals($expectedLog, $this->loggedOutput);
    }

    public function testNonMultimediaType()
    {
        $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'text/plain',
        ];
        $file = $this->mockFile();
        $message = 'The file is not a multimedia file';
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $this->setExpectedException(FileException::class, $message);
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testConfigurationWithoutType()
    {
        $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $message = "Configuration for type video is undefined or malformed";
        $this->setExpectedException(InvalidConfigurationException::class, $message);
        unset($this->converterParams['video']);
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testConfigurationWithoutExtension()
    {
        $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $message = "Configuration for type video is undefined or malformed";
        $this->setExpectedException(InvalidConfigurationException::class, $message);
        unset($this->converterParams['video']['extension']);
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testBadReturnCode()
    {
        $this->mockShellDecorator(false);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $this->shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $this->setExpectedException(\RuntimeException::class, 'FFMPEG error: All is bad');
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file');
    }

    protected function mockShellDecorator($isSuccessful)
    {
        $this->shellDecorator = $this
            ->getMockBuilder(ShellDecorator::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($isSuccessful) {
            $this->shellDecorator->expects($this->any())
                ->method('exec')
                ->will($this->returnCallback([$this, 'execMockSuccessfulCallback']));
            return;
        }
        $this->shellDecorator->expects($this->any())
            ->method('exec')
            ->will($this->returnCallback([$this, 'execMockFailedCallback']));
    }

    protected function mockLogger()
    {
        $this->logger = $this
            ->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger->expects($this->any())
            ->method('addInfo')
            ->will($this->returnCallback([$this, 'loggerAddInfoCallback']));
    }

    protected function mockCustomLogger()
    {
        $this->customLogger = $this
            ->getMockBuilder(CustomLogger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customLogger->expects($this->any())
            ->method('setLogger')
            ->will($this->returnValue($this->logger));
    }

    protected function mockFile()
    {
        $file = $this
            ->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $file->expects($this->any())
            ->method('getFilename')
            ->will($this->returnCallback([$this, 'fileGetNameCallback']));
        $file->expects($this->any())
            ->method('getRealPath')
            ->will($this->returnCallback([$this, 'fileGetRealPathCallback']));
        $file->expects($this->any())
            ->method('getMimeType')
            ->will($this->returnCallback([$this, 'fileGetMimeTypeCallback']));
        return $file;
    }

    public function execMockSuccessfulCallback($command, &$output, &$return_var)
    {
        $output = ['All is OK'];
        $return_var = '0';
    }

    public function execMockFailedCallback($command, &$output, &$return_var)
    {
        $output = ['All is bad'];
        $return_var = 'non-integer error';
    }

    public function loggerAddInfoCallback($message)
    {
        $this->loggedOutput = $message;
    }

    public function fileGetNameCallback()
    {
        $filename = pathinfo($this->fileParams['source'], PATHINFO_BASENAME);
        return $filename;
    }

    public function fileGetRealPathCallback()
    {
        return $this->fileParams['source'];
    }

    public function fileGetMimeTypeCallback()
    {
        return $this->fileParams['mime_type'];
    }
}
