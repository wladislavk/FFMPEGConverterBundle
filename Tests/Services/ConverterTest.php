<?php
namespace VKR\FFMPEGConverterBundle\Tests\Services;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use VKR\CustomLoggerBundle\Services\CustomLogger;
use VKR\FFMPEGConverterBundle\Decorators\ShellDecorator;
use VKR\FFMPEGConverterBundle\Services\Converter;

class ConverterTest extends TestCase
{
    const SOURCE_FILE = __DIR__ . '/../../TestHelpers/static/source/test.csv';
    const DESTINATION_DIR = __DIR__ . '/../../TestHelpers/static/destination/';

    const FFMPEG_PATH = '/usr/bin/ffmpeg';

    private $converterParams = [
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

    private $customLogger;

    /**
     * @var string
     */
    private $loggedOutput;

    /**
     * @var array
     */
    private $fileParams;

    public function setUp()
    {
        $this->customLogger = $this->mockCustomLogger();
    }

    public function testVideoFormat()
    {
        $shellDecorator = $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
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
        $shellDecorator = $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'image/jpg',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
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
        $shellDecorator = $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'text/plain',
        ];
        $file = $this->mockFile();
        $message = 'The file is not a multimedia file';
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $this->expectException(FileException::class);
        $this->expectExceptionMessage($message);
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testConfigurationWithoutType()
    {
        $shellDecorator = $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $message = "Configuration for type video is undefined or malformed";
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        unset($this->converterParams['video']);
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testConfigurationWithoutExtension()
    {
        $shellDecorator = $this->mockShellDecorator(true);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $message = "Configuration for type video is undefined or malformed";
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        unset($this->converterParams['video']['extension']);
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', 30);
    }

    public function testBadReturnCode()
    {
        $shellDecorator = $this->mockShellDecorator(false);
        $this->fileParams = [
            'source' => self::SOURCE_FILE,
            'mime_type' => 'video/mp4',
        ];
        $file = $this->mockFile();
        $converter = new Converter(
            $this->customLogger, $shellDecorator, self::FFMPEG_PATH, $this->converterParams
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FFMPEG error: All is bad');
        $converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file');
    }

    private function mockShellDecorator($isSuccessful)
    {
        $shellDecorator = $this->createMock(ShellDecorator::class);
        if ($isSuccessful) {
            $shellDecorator->method('exec')
                ->willReturnCallback([$this, 'execMockSuccessfulCallback']);
            return $shellDecorator;
        }
        $shellDecorator->method('exec')
            ->willReturnCallback([$this, 'execMockFailedCallback']);
        return $shellDecorator;
    }

    private function mockLogger()
    {
        $logger = $this
            ->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $logger->expects($this->any())
            ->method('addInfo')
            ->will($this->returnCallback([$this, 'loggerAddInfoCallback']));
        return $logger;
    }

    private function mockCustomLogger()
    {
        $customLogger = $this->createMock(CustomLogger::class);
        $customLogger->method('setLogger')->willReturn($this->mockLogger());
        return $customLogger;
    }

    private function mockFile()
    {
        $file = $this->createMock(File::class);
        $file->method('getFilename')
            ->willReturnCallback([$this, 'fileGetNameCallback']);
        $file->method('getRealPath')
            ->willReturnCallback([$this, 'fileGetRealPathCallback']);
        $file->method('getMimeType')
            ->willReturnCallback([$this, 'fileGetMimeTypeCallback']);
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
