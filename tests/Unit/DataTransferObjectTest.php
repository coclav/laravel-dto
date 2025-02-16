<?php

namespace OpenSoutheners\LaravelDto\Tests\Unit;

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use OpenSoutheners\LaravelDto\Tests\Fixtures\CreatePostData;
use OpenSoutheners\LaravelDto\Tests\Fixtures\PostStatus;
use PHPUnit\Framework\TestCase;

class DataTransferObjectTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $mockedConfig = Mockery::mock(Repository::class);

        $mockedConfig->shouldReceive('get')->andReturn(true);

        Container::getInstance()->bind('config', fn () => $mockedConfig);

        $mockedAuth = Mockery::mock(AuthManager::class);

        $mockedAuth->shouldReceive('check')->andReturn(false);

        Container::getInstance()->bind('auth', fn () => $mockedAuth);
    }

    public function testDataTransferObjectFromArray()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => 'foo,bar,test',
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertTrue($data->postStatus instanceof PostStatus);
        $this->assertEquals('Hello world', $data->title);
        $this->assertIsArray($data->tags);
        $this->assertContains('bar', $data->tags);
        $this->assertNull($data->post);
    }

    public function testDataTransferObjectFromArrayDelimitedLists()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => 'foo',
            'country' => 'foo',
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertIsArray($data->tags);
        $this->assertIsString($data->country);
    }

    public function testDataTransferObjectFilledViaClassProperties()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'author_email' => 'me@d8vjork.com',
        ]);

        $this->assertTrue($data->filled('tags'));
        $this->assertTrue($data->filled('postStatus'));
        $this->assertFalse($data->filled('post'));
        $this->assertFalse($data->filled('description'));
        $this->assertFalse($data->filled('author_email'));
    }

    public function testDataTransferObjectWithDefaults()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertContains('generic', $data->tags);
        $this->assertContains('post', $data->tags);
    }

    public function testDataTransferObjectArrayWithoutTypedPropertiesGetsThroughWithoutChanges()
    {
        $helloTag = [
            'name' => 'Hello world',
            'slug' => 'hello-world'
        ];

        $travelingTag = [
            'name' => 'Traveling guides',
            'slug' => 'traveling-guides'
        ];

        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => [
                $helloTag,
                $travelingTag,
            ],
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertContains($helloTag, $data->tags);
        $this->assertContains($travelingTag, $data->tags);
    }

    public function testDataTransferObjectArrayPropertiesGetsMappedAsCollection()
    {
        $rubenUser = [
            'name' => 'Rubén Robles',
            'email' => 'ruben@hello.com'
        ];

        $taylorUser = [
            'name' => 'Taylor Otwell',
            'email' => 'taylor@hello.com'
        ];

        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'subscribers' => [
                $rubenUser,
                $taylorUser,
            ],
            'post_status' => PostStatus::Published->value,
        ]);

        $this->assertTrue($data->subscribers instanceof Collection);
        $this->assertContains($rubenUser, $data->subscribers);
        $this->assertContains($taylorUser, $data->subscribers);
    }

    public function testDataTransferObjectDatePropertiesGetMappedFromStringsIntoCarbonInstances()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'published_at' => '2023-09-06 17:35:53',
            'content' => '{"type": "doc", "content": [{"type": "paragraph", "attrs": {"textAlign": "left"}, "content": [{"text": "dede", "type": "text"}]}]}'
        ]);

        $this->assertTrue($data->publishedAt instanceof Carbon);
        $this->assertTrue(now()->isAfter($data->publishedAt));
    }

    public function testDataTransferObjectDatePropertiesGetMappedFromJsonStringsIntoGenericObjects()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'content' => '{"type": "doc", "content": [{"type": "paragraph", "attrs": {"textAlign": "left"}, "content": [{"text": "hello world", "type": "text"}]}]}'
        ]);

        $this->assertTrue($data->content instanceof \stdClass);
        $this->assertObjectHasProperty('type', $data->content);
    }

    public function testDataTransferObjectDatePropertiesGetMappedFromArraysIntoGenericObjects()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'content' => [
                "type" => "doc",
                "content" => [
                    [
                        "type" => "paragraph",
                        "attrs" => [
                            "textAlign" => "left",
                        ],
                        "content" => [
                            [
                                "text" => "hello world",
                                "type" => "text",
                            ],
                        ],
                    ],
                ],
            ]
        ]);

        $this->assertTrue($data->content instanceof \stdClass);
        $this->assertObjectHasProperty('type', $data->content);
    }

    public function testDataTransferObjectDatePropertiesGetMappedFromArraysOfObjectsIntoCollectionOfGenericObjects()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'dates' => [
                '2023-09-06 17:35:53',
                '2023-09-07 06:35:53',
            ]
        ]);

        $this->assertTrue($data->dates instanceof Collection);
        $this->assertTrue($data->dates->first() instanceof Carbon);
        $this->assertTrue(now()->isAfter($data->dates->first()));
        $this->assertTrue(now()->isAfter($data->dates->last()));
    }

    public function testDataTransferObjectDatePropertiesDoesNotGetMappedFromCollectionsToSameType()
    {
        $data = CreatePostData::fromArray([
            'title' => 'Hello world',
            'tags' => '',
            'post_status' => PostStatus::Published->value,
            'dates' => Collection::make([
                '2023-09-06 17:35:53',
                '2023-09-07 06:35:53',
            ])
        ]);

        $this->assertTrue($data->dates instanceof Collection);
        $this->assertFalse($data->dates->first() instanceof Carbon);
        $this->assertIsString($data->dates->first());
    }
}
