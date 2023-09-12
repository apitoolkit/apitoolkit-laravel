<?php

namespace APIToolkit\Http\Middleware;

use Orchestra\Testbench\TestCase;
use APIToolkit\Http\Middleware\APIToolkit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Google\Cloud\PubSub\Topic;
use Mockery;

/**
 * @covers APIToolkit\Http\Middleware
 */
class APIToolkitTest extends TestCase
{
  // Define environment setup
  protected function getEnvironmentSetUp($app)
  {
    // setup environment here
  }

  protected function tearDown(): void
  {
    Mockery::close();  // Close Mockery session after each test
  }
  private $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees",
            "title": "Sayings of the Century",
            "price": 8.95,
            "available": true
          },
          { "category": "fiction",
            "author": "Evelyn Waugh",
            "title": "Sword of Honour",
            "price": 12.99,
            "available": false
          }
        ],
        "bicycle": {
          "color": "red",
          "price": 19.95,
          "available": true
        }
      },
      "authors": [
        "Nigel Rees",
        "Herman Melville",
        "J. R. R. Tolkien"
      ]
    }
    ';

  public function test_empty_redected_json_same_as_input(): void
  {
    $svc = new APIToolkit("");
    $redactedJSON = $svc->redactJSONFields([], $this->testJSON);
    $this->assertJsonStringEqualsJsonString($this->testJSON, $redactedJSON);
  }

  public function test_redacted_field(): void
  {
    $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees",
            "title": "Sayings of the Century",
            "price": 8.95,
            "available": true
          },
          { "category": "fiction",
            "author": "Evelyn Waugh",
            "title": "Sword of Honour",
            "price": 12.99,
            "available": false
          }
        ]
      }
    }
    ';
    $expectedJSON = '{ "store": {"book": "[CLIENT_REDACTED]"}}';
    $svc = new APIToolkit("");
    $redactedJSON = $svc->redactJSONFields(['$.store.book'], $testJSON);
    $this->assertJsonStringEqualsJsonString($expectedJSON, $redactedJSON);
  }

  public function test_redacted_array_subfield(): void
  {
    $testJSON = '
    { "store": {
        "book": [
          { "category": "reference",
            "author": "Nigel Rees"
          },
          { "category": "fiction",
            "author": "Evelyn Waugh"
          }
        ]
      }
    }
    ';
    $expectedJSON = '
    { "store": {
        "book": [
          { "category": "[CLIENT_REDACTED]",
            "author": "Nigel Rees"
          },
          { "category": "[CLIENT_REDACTED]",
            "author": "Evelyn Waugh"
          }
        ]
      }
    }
    ';
    $svc = new APIToolkit("");
    $redactedJSON = $svc->redactJSONFields(['$.store.book[*].category'], $testJSON);
    $this->assertJsonStringEqualsJsonString($expectedJSON, $redactedJSON);
  }

  public function test_return_invalid_json_as_is(): void
  {
    $testJSON = 'invalid_json';
    $expectedJSON = 'invalid_json';
    $svc = new APIToolkit("");
    $redactedJSON = $svc->redactJSONFields(['$.store.book[*].category'], $testJSON);
    $this->assertEquals($expectedJSON, $redactedJSON);
  }

  // Register package service providers
  protected function getPackageProviders($app)
  {
    return [\APIToolkit\Provider\APIToolkitServiceProvider::class];
  }

  public function testLog()
  {
    $request = Request::create('/example', 'GET', [
      'query_param1' => 'value1',
      'query_param2' => 'value2',
    ], [], [], [
      'HTTP_Custom-Header' => 'CustomValue',
      'HTTP_Authorization' => 'Bearer blabla',
    ]);
    $response = new Response('Body content here', 200, [
      'Content-Type' => 'application/json',
      'Custom-Header' => 'CustomValue',
    ]);
    $mockedTopic = $this->createMock(Topic::class);
    $mockedTopic->expects($this->once())
      ->method('publish')
      ->with($this->callback(function ($subjectStr) {
        $data = json_decode($subjectStr['data']);
        $this->assertEquals($data -> status_code, 200);
        $this->assertEquals($data->method, "GET");
        $this->assertEquals($data->raw_url, "/example?query_param1=value1&query_param2=value2");
        $this->assertEquals($data->url_path, "/example?query_param1=value1&query_param2=value2");
        $this->assertEquals($data->query_params -> query_param1, "value1");
        $this->assertEquals($data->query_params -> query_param2, "value2");
        // $this->assertEquals($data->request_headers->'custom-header'[0], "CustomValue");
        $this->assertEquals($data->response_body, base64_encode("Body content here"));
        return isset($subjectStr['data']);
      }));



    $apiToolkit = new APIToolkit();
    $apiToolkit->debug = false;
    $apiToolkit->pubsubTopic = $mockedTopic;
    $apiToolkit->log($request, $response, hrtime(true));
  }
}
