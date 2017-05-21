<?php
namespace VKR\FFMPEGConverterBundle\Tests\Functional;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;
use VKR\CustomLoggerBundle\Services\CustomLogger;
use VKR\FFMPEGConverterBundle\Decorators\ShellDecorator;
use VKR\FFMPEGConverterBundle\Services\Converter;

class FFMPEGFunctionalTest extends WebTestCase
{
    const DESTINATION_DIR = __DIR__ . '/../../TestHelpers/static/destination/';

    /**
     * @var Converter
     */
    private $converter;

    /**
     * @var string
     */
    private $loggedOutput;

    public function setUp()
    {
        $customLogger = $this->mockCustomLogger();
        $client = static::createClient();
        $container = $client->getContainer();
        $shellDecorator = new ShellDecorator();
        $converterParams = $container->getParameter('vkr_ffmpeg_converter.params');
        $ffmpegPath = $container->getParameter('ffmpeg_path');
        $this->converter = new Converter(
            $customLogger, $shellDecorator, $ffmpegPath, $converterParams
        );
    }

    public function testImageConversion()
    {
        $file = new File(__DIR__ . '/../../TestHelpers/static/source/my_image.jpg');
        $length = 30;
        $this->converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', $length);
        $newFilename = 'my_image.mp4';
        $this->assertTrue(is_file(self::DESTINATION_DIR . $newFilename));
        $this->assertContains('Converted', $this->loggedOutput);
        $handler = new File(self::DESTINATION_DIR . $newFilename);
        $this->assertTrue($handler->getSize() > 0);
        $this->assertEquals('video/mp4', $handler->getMimeType());
    }

    // there might be a problem when converting images with non-even width or height
    public function testImageWithNonEvenWidth()
    {
        $file = new File(__DIR__ . '/../../TestHelpers/static/source/my_other_image.jpg');
        $length = 30;
        $this->converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file', $length);
        $newFilename = 'my_other_image.mp4';
        $this->assertTrue(is_file(self::DESTINATION_DIR . $newFilename));
        $this->assertContains('Converted', $this->loggedOutput);
        $handler = new File(self::DESTINATION_DIR . $newFilename);
        $this->assertTrue($handler->getSize() > 0);
        $this->assertEquals('video/mp4', $handler->getMimeType());
    }

    public function testVideoConversion()
    {
        $file = new File(__DIR__ . '/../../TestHelpers/static/source/my_video.webm');
        $this->converter->convertFile($file, self::DESTINATION_DIR, 'some_log_file');
        $newFilename = 'my_video.mp4';
        $this->assertTrue(is_file(self::DESTINATION_DIR . $newFilename));
        $this->assertContains('Converted', $this->loggedOutput);
        $handler = new File(self::DESTINATION_DIR . $newFilename);
        $this->assertTrue($handler->getSize() > 0);
        $this->assertEquals('video/mp4', $handler->getMimeType());
    }

    public function tearDown()
    {
        $videoFile = self::DESTINATION_DIR . 'my_video.mp4';
        if (is_file($videoFile)) {
            unlink($videoFile);
        }
        $imageFile = self::DESTINATION_DIR . 'my_image.mp4';
        if (is_file($imageFile)) {
            unlink($imageFile);
        }
        $otherImageFile = self::DESTINATION_DIR . 'my_other_image.mp4';
        if (is_file($otherImageFile)) {
            unlink($otherImageFile);
        }
    }

    private function mockLogger()
    {
        $logger = $this->createMock(Logger::class);
        $logger->method('addInfo')
            ->willReturnCallback([$this, 'loggerAddInfoCallback']);
        return $logger;
    }

    private function mockCustomLogger()
    {
        $customLogger = $this->createMock(CustomLogger::class);
        $customLogger->method('setLogger')->willReturn($this->mockLogger());
        return $customLogger;
    }

    public function loggerAddInfoCallback($message)
    {
        $this->loggedOutput = $message;
    }
}
