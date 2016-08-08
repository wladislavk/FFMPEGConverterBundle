About
=====

This is a simple bundle that handles conversion of multimedia formats using the popular
FFMPEG library. It depends on VKRCustomLoggerBundle to function.

Installation
============

Besides enabling the bundle in ```AppKernel.php```, you will also need to create to do some
configuration.

First, add the following to ```parameters.yml```:

```
ffmpeg_path: /path/to/ffmpeg/executable
```

Second, you will need to add FFMPEG arguments to ```config.yml``` under ```vkr_ffmpeg_converter``` key.
There can be three groups of arguments: ```video```, ```audio``` and ```image```. Inside
each one, there should be three keys: ```extension``` corresponds to the desired destination
file extension, ```input``` and ```output``` are series of arguments for FFMPEG.

In FFMPEG, unlike most other command-line tools, arguments are not idempotent, in other
words, argument's behavior depends on its position in the arguments list. Input arguments
are the ones that go before the source filename, output arguments go between input and output
file names. For details, refer to [FFMPEG manual](https://ffmpeg.org/documentation.html).

Note that you do not have to specify ```-i``` argument in your config.

Finally, you need to create a log file in your ```app/logs``` folder and make it script-
writable.

Usage
=====

The usage is very simple. Just write this in your controller:

```
$converter = $this->get('vkr_ffmpeg_converter.converter');
$sourceFile = new Symfony\Component\HttpFoundation\File\File('source/filename/with.path');
$destination = '/path/to/destination/dir/';
// corresponds to /app/logs/video_converter.log
$logFile = 'video_converter';
$converter->convertFile($sourceFile, $destination, $logFile);
```

You may also provide fourth argument to ```convertFile()``` which is maximum output file
length in seconds (same as ```-t``` input argument).

Testing
=======

It is highly recommended that you run functional tests shipped with this bundle before
writing your production code. Functional tests will help you determine if your configuration
is correct as well as if this bundle will work with your version of FFMPEG.

Currently, the functional test suite provides conversions between WEBM and MP4 and
between JPEG and MP4. There are no audio conversions available.

To run tests, add the following to ```config_test.yml``` file:

```
imports:
    - { resource: parameters_test.yml }
```

Create ```app/config/parameters_test.yml``` file and add the following:

```
parameters:
    vkr_ffmpeg_converter:
        video:
            extension: 'mp4'
            input: ''
            output: '-c:v libx264 -crf 20'
        image:
            extension: 'mp4'
            input: '-loop 1'
            output: '-c:v libx264 -pix_fmt yuv420p -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"'
```

Run ```phpunit -c app/ vendor/vkr/ffmpeg-converter-bundle/Tests``` and see if all tests are green.

Re-run the tests after deployment to a live server.

Known limitations
=================

- There is only one set of settings per multimedia type. Therefore, you cannot specify
different arguments for different input file types or multiple ways of conversion.
- There can be only one input file. Therefore, you cannot do such things as gluing multiple
files together.
