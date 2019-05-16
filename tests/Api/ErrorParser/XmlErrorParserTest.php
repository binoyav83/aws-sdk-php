<?php
namespace Aws\Test\Api\ErrorParser;

use Aws\Api\ErrorParser\XmlErrorParser;
use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

/**
 * @covers Aws\Api\ErrorParser\XmlErrorParser
 */
class XmlErrorParserTest extends TestCase
{
    use ErrorParserTestServiceTrait;

    /**
     * @dataProvider errorResponsesProvider
     *
     * @param $response
     * @param $command
     * @param $parser
     * @param $expected
     */
    public function testParsesClientErrorResponses(
        $response,
        $command,
        $parser,
        $expected
    ) {
        $response = Psr7\parse_response($response);
        $result = $parser($response, $command);
        $this->assertArraySubset($expected, $result);
        $this->assertInstanceOf('SimpleXMLElement', $result['parsed']);
    }

    public function errorResponsesProvider()
    {
        $ec2Service = $this->generateTestService('ec2');
        $ec2Client = $this->generateTestClient($ec2Service);
        $ec2Command = $ec2Client->getCommand('TestOperation', []);

        $queryService = $this->generateTestService('query');
        $queryClient = $this->generateTestClient($queryService);
        $queryCommand = $queryClient->getCommand('TestOperation', []);

        $restXmlService = $this->generateTestService('query');
        $restXmlClient = $this->generateTestClient($restXmlService);
        $restXmlCommand = $restXmlClient->getCommand('TestOperation', []);

        return [
            // ec2, modeled exception
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                '<Response>' .
                '  <Errors>' .
                '    <Error>' .
                '      <Code>TestException</Code>' .
                '      <Message>Error Message</Message>' .
                '      <TestString>SomeString</TestString>' .
                '      <TestInt>456</TestInt>' .
                '    </Error>' .
                '  </Errors>' .
                '  <RequestId>xyz</RequestId>' .
                '</Response>',
                $ec2Command,
                new XmlErrorParser($ec2Service),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'TestException',
                    'message' => 'Error Message',
                    'body' => [
                        'TestHeaderMember' => 'foo-header',
                        'TestHeaders' => [
                            'foo' => 'foo-meta',
                            'bar' => 'bar-meta',
                        ],
                        'TestStatus' => 400,
                        'TestString' => 'SomeString',
                        'TestInt' => 456
                    ],
                ],
            ],
            // ec2, no modeled shape
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                '<Response>' .
                '  <Errors>' .
                '    <Error>' .
                '      <Code>NonExistentException</Code>' .
                '      <Message>Error Message</Message>' .
                '      <TestString>SomeString</TestString>' .
                '      <TestInt>456</TestInt>' .
                '    </Error>' .
                '  </Errors>' .
                '  <RequestId>xyz</RequestId>' .
                '</Response>',
                $ec2Command,
                new XmlErrorParser($ec2Service),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'NonExistentException',
                    'message' => 'Error Message',
                ],
            ],
            // query, modeled exception
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<ErrorResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/">' .
                '  <Error>' .
                '    <Type>ErrorType</Type>' .
                '    <Code>TestException</Code>' .
                '    <Message>Error Message</Message>' .
                '    <TestString>SomeString</TestString>' .
                '    <TestInt>456</TestInt>' .
                '  </Error>' .
                '  <RequestId>xyz</RequestId>' .
                '</ErrorResponse>',
                $queryCommand,
                new XmlErrorParser($queryService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'TestException',
                    'message' => 'Error Message',
                    'body' => [
                        'TestHeaderMember' => 'foo-header',
                        'TestHeaders' => [
                            'foo' => 'foo-meta',
                            'bar' => 'bar-meta',
                        ],
                        'TestStatus' => 400,
                        'TestString' => 'SomeString',
                        'TestInt' => 456
                    ],
                ],
            ],
            // query, no modeled shape
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<ErrorResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/">' .
                '  <Error>' .
                '    <Type>ErrorType</Type>' .
                '    <Code>NonExistentException</Code>' .
                '    <Message>Error Message</Message>' .
                '    <TestString>SomeString</TestString>' .
                '    <TestInt>456</TestInt>' .
                '  </Error>' .
                '  <RequestId>xyz</RequestId>' .
                '</ErrorResponse>',
                $queryCommand,
                new XmlErrorParser($queryService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'NonExistentException',
                    'message' => 'Error Message',
                ],
            ],
            // rest-xml, modeled exception
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<ErrorResponse xmlns="http://cloudfront.amazonaws.com/doc/2016-09-07/">' .
                '  <Error>' .
                '    <Type>ErrorType</Type>' .
                '    <Code>TestException</Code>' .
                '    <Message>Error Message</Message>' .
                '    <TestString>SomeString</TestString>' .
                '    <TestInt>456</TestInt>' .
                '  </Error>' .
                '  <RequestId>xyz</RequestId>' .
                '</ErrorResponse>',
                $restXmlCommand,
                new XmlErrorParser($restXmlService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'TestException',
                    'message' => 'Error Message',
                    'body' => [
                        'TestHeaderMember'  => 'foo-header',
                        'TestHeaders'       => [
                            'foo' => 'foo-meta',
                            'bar' => 'bar-meta',
                        ],
                        'TestStatus' => 400,
                        'TestString' => 'SomeString',
                        'TestInt' => 456
                    ],
                ],
            ],
            // rest-xml, no modeled shape
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<ErrorResponse xmlns="http://cloudfront.amazonaws.com/doc/2016-09-07/">' .
                '  <Error>' .
                '    <Type>ErrorType</Type>' .
                '    <Code>NonExistentException</Code>' .
                '    <Message>Error Message</Message>' .
                '    <TestString>SomeString</TestString>' .
                '    <TestInt>456</TestInt>' .
                '  </Error>' .
                '  <RequestId>xyz</RequestId>' .
                '</ErrorResponse>',
                $restXmlCommand,
                new XmlErrorParser($restXmlService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'NonExistentException',
                    'message' => 'Error Message',
                ],
            ],
            // legacy format, modeled exception
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<Error>' .
                '  <Type>ErrorType</Type>' .
                '  <Code>TestException</Code>' .
                '  <Message>Error Message</Message>' .
                '  <RequestId>xyz</RequestId>' .
                '  <Resource>Foo</Resource>' .
                '  <TestString>SomeString</TestString>' .
                '  <TestInt>456</TestInt>' .
                '</Error>',
                $restXmlCommand,
                new XmlErrorParser($restXmlService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'TestException',
                    'message' => 'Error Message',
                    'body' => [
                        'TestHeaderMember'  => 'foo-header',
                        'TestHeaders'       => [
                            'foo' => 'foo-meta',
                            'bar' => 'bar-meta',
                        ],
                        'TestStatus' => 400,
                        'TestString' => 'SomeString',
                        'TestInt' => 456
                    ],
                ],
            ],
            // legacy format, no modeled shape
            [
                "HTTP/1.1 400 Bad Request\r\n" .
                "TestHeader: foo-header\r\n" .
                "x-meta-foo: foo-meta\r\n" .
                "x-meta-bar: bar-meta\r\n" .
                "x-amzn-requestid: xyz\r\n\r\n" .
                '<Error>' .
                '  <Type>ErrorType</Type>' .
                '  <Code>NonExistentException</Code>' .
                '  <Message>Error Message</Message>' .
                '  <RequestId>xyz</RequestId>' .
                '  <Resource>Foo</Resource>' .
                '  <TestString>SomeString</TestString>' .
                '  <TestInt>456</TestInt>' .
                '</Error>',
                $restXmlCommand,
                new XmlErrorParser($restXmlService),
                [
                    'type' => 'client',
                    'request_id' => 'xyz',
                    'code' => 'NonExistentException',
                    'message' => 'Error Message',
                ],
            ],
        ];
    }

    public function testParsesResponsesWithNoBodyAndNoRequestId()
    {
        $response = Psr7\parse_response(
            "HTTP/1.1 400 Bad Request\r\n\r\n"
        );
        $parser = new XmlErrorParser();
        $result = $parser($response);
        $this->assertEquals('400 Bad Request', $result['message']);
        $this->assertNull($result['parsed']);
    }

    public function testParsesResponsesWithNoBody()
    {
        $response = $response = Psr7\parse_response(
            "HTTP/1.1 400 Bad Request\r\nX-Amz-Request-ID: Foo\r\n\r\n"
        );
        $parser = new XmlErrorParser();
        $result = $parser($response);
        $this->assertEquals('400 Bad Request (Request-ID: Foo)', $result['message']);
        $this->assertEquals('Foo', $result['request_id']);
    }

    public function testUsesNotFoundWhen404()
    {
        $response = $response = Psr7\parse_response(
            "HTTP/1.1 404 Not Found\r\nX-Amz-Request-ID: Foo\r\n\r\n"
        );
        $parser = new XmlErrorParser();
        $result = $parser($response);
        $this->assertEquals('NotFound', $result['code']);
    }
}
