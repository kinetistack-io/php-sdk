<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\SearchFilterDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\SearchResponseDto;
use KinetiStack\Sdk\Tests\FixtureTrait;
use PHPUnit\Framework\TestCase;

class SearchResultDtoTest extends TestCase
{
    use FixtureTrait;

    public function testSearchQueryDtoConstructorAndCreate(): void
    {
        $dto = SearchQueryDto::create('machine learning');

        $this->assertSame('machine learning', $dto->query);
        $this->assertNull($dto->limit);
        $this->assertNull($dto->locale);
        $this->assertNull($dto->userRoles);
        $this->assertNull($dto->minScore);
        $this->assertNull($dto->distinctDocuments);
        $this->assertNull($dto->synthesizeAnswer);
        $this->assertNull($dto->filters);
    }

    public function testSearchQueryDtoEmptyQueryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty.');

        SearchQueryDto::create('   ');
    }

    public function testSearchQueryDtoFluentBuilderImmutability(): void
    {
        $initial = SearchQueryDto::create('base query');

        $step1 = $initial->withLimit(25);
        $this->assertNotSame($initial, $step1);
        $this->assertSame(25, $step1->limit);

        $step2 = $step1->withLocale('da');
        $this->assertSame('da', $step2->locale);

        $step3 = $step2->withUserRoles(['admin', 'subscriber']);
        $this->assertSame(['admin', 'subscriber'], $step3->userRoles);

        $step3SingleRole = $step2->withUserRoles('admin');
        $this->assertSame(['admin'], $step3SingleRole->userRoles);

        $step4 = $step3->withMinScore(0.82);
        $this->assertSame(0.82, $step4->minScore);

        $step5 = $step4->withDistinctDocuments(true);
        $this->assertTrue($step5->distinctDocuments);

        $step6 = $step5->withSynthesis(true);
        $this->assertTrue($step6->synthesizeAnswer);

        $step7 = $step6->withSynthesizeAnswer(false);
        $this->assertFalse($step7->synthesizeAnswer);

        $step8 = $step7->withPermissions(['authenticated']);
        $this->assertInstanceOf(SearchFilterDto::class, $step8->filters);
        $this->assertSame(['authenticated'], $step8->filters->permissions);

        $step8SinglePermission = $step7->withPermissions('authenticated');
        $this->assertInstanceOf(SearchFilterDto::class, $step8SinglePermission->filters);
        $this->assertSame(['authenticated'], $step8SinglePermission->filters->permissions);

        $step9 = $step8->withFilter('bundle', 'article');
        $this->assertInstanceOf(SearchFilterDto::class, $step9->filters);
        $this->assertSame(['bundle' => 'article'], $step9->filters->custom);

        $customFilter = new SearchFilterDto(locale: 'da');
        $step10 = $step9->withFilters($customFilter);
        $this->assertSame($customFilter, $step10->filters);

        $step11 = $step9->withFilters(['permissions' => ['staff'], 'category' => 'tech']);
        $this->assertInstanceOf(SearchFilterDto::class, $step11->filters);
        $this->assertSame(['staff'], $step11->filters->permissions);
        $this->assertSame(['category' => 'tech'], $step11->filters->custom);
    }

    public function testSearchQueryDtoToArrayAndFromArray(): void
    {
        $query = SearchQueryDto::create('full test')
            ->withLimit(10)
            ->withLocale('en')
            ->withUserRoles(['editor'])
            ->withMinScore(0.75)
            ->withDistinctDocuments(true)
            ->withSynthesis(true)
            ->withFilter('bundle', 'article')
            ->withPermissions(['public']);

        $array = $query->toArray();

        $this->assertSame('full test', $array['query']);
        $this->assertSame(10, $array['limit']);
        $this->assertSame('en', $array['locale']);
        $this->assertSame(['editor'], $array['user_roles']);
        $this->assertSame(0.75, $array['min_score']);
        $this->assertTrue($array['distinct_documents']);
        $this->assertTrue($array['synthesize_answer']);
        $this->assertSame(['public'], $array['filters']['permissions']);
        $this->assertSame('article', $array['filters']['bundle']);

        $restored = SearchQueryDto::fromArray($array);
        $this->assertSame('full test', $restored->query);
        $this->assertSame(10, $restored->limit);
        $this->assertSame('en', $restored->locale);
        $this->assertSame(['editor'], $restored->userRoles);
        $this->assertSame(0.75, $restored->minScore);
        $this->assertTrue($restored->distinctDocuments);
        $this->assertTrue($restored->synthesizeAnswer);
        $this->assertInstanceOf(SearchFilterDto::class, $restored->filters);
        $this->assertSame(['public'], $restored->filters->permissions);
        $this->assertSame(['bundle' => 'article'], $restored->filters->custom);
    }

    public function testSearchFilterDtoImmutabilityAndBuilder(): void
    {
        $filter = new SearchFilterDto();
        $this->assertTrue($filter->isEmpty());

        $f1 = $filter->withLocale('en');
        $this->assertFalse($f1->isEmpty());
        $this->assertSame('en', $f1->locale);

        $f2 = $f1->withPermissions(['admin', 'editor']);
        $this->assertSame(['admin', 'editor'], $f2->permissions);

        $f2Single = $f1->withPermissions('admin');
        $this->assertSame(['admin'], $f2Single->permissions);

        $f3 = $f2->withCustom('tag', 'solar');
        $this->assertSame(['tag' => 'solar'], $f3->custom);

        $f4 = $f3->withCustomFilters(['tag' => 'energy', 'archived' => false]);
        $this->assertSame(['tag' => 'energy', 'archived' => false], $f4->custom);

        $array = $f4->toArray();
        $this->assertSame('en', $array['locale']);
        $this->assertSame(['admin', 'editor'], $array['permissions']);
        $this->assertSame('energy', $array['tag']);
        $this->assertFalse($array['archived']);

        $restored = SearchFilterDto::fromArray($array);
        $this->assertSame('en', $restored->locale);
        $this->assertSame(['admin', 'editor'], $restored->permissions);
        $this->assertSame(['tag' => 'energy', 'archived' => false], $restored->custom);
    }

    public function testSearchResultItemDtoConstructorAndSnippet(): void
    {
        $dto = new SearchResultItemDto(
            externalId: 'node:123',
            title: 'Test Title',
            content: 'Detailed text match',
            chunkIndex: 2,
            score: 0.91,
            metadata: ['author' => 'Alice']
        );

        $this->assertSame('node:123', $dto->externalId);
        $this->assertSame('Test Title', $dto->title);
        $this->assertSame('Detailed text match', $dto->content);
        $this->assertSame('Detailed text match', $dto->getSnippet());
        $this->assertSame(2, $dto->chunkIndex);
        $this->assertSame(0.91, $dto->score);
        $this->assertSame(['author' => 'Alice'], $dto->metadata);

        $array = $dto->toArray();
        $this->assertSame('node:123', $array['external_id']);
        $this->assertSame('Test Title', $array['title']);
        $this->assertSame('Detailed text match', $array['content']);
        $this->assertSame(2, $array['chunk_index']);
        $this->assertSame(0.91, $array['score']);
        $this->assertSame(['author' => 'Alice'], $array['metadata']);
    }

    public function testSearchResultItemDtoFromArrayWithoutMetadata(): void
    {
        $dto = SearchResultItemDto::fromArray([
            'external_id' => 'doc:5',
            'title' => 'Doc 5',
            'content' => 'Snippet text',
            'chunk_index' => 0,
            'score' => 0.88,
        ]);

        $this->assertSame('doc:5', $dto->externalId);
        $this->assertNull($dto->metadata);
        $this->assertArrayNotHasKey('metadata', $dto->toArray());
    }

    public function testRagSynthesisDtoSuccessAndFallback(): void
    {
        $successDto = new RagSynthesisDto(
            answer: 'Generated answer.',
            citations: ['node:1', 'node:2'],
            successful: true,
            fallbackReason: null
        );

        $this->assertSame('Generated answer.', $successDto->answer);
        $this->assertSame(['node:1', 'node:2'], $successDto->citations);
        $this->assertTrue($successDto->successful);
        $this->assertNull($successDto->fallbackReason);

        $successArray = $successDto->toArray();
        $this->assertSame('Generated answer.', $successArray['answer']);
        $this->assertSame(['node:1', 'node:2'], $successArray['citations']);
        $this->assertTrue($successArray['successful']);
        $this->assertArrayNotHasKey('fallback_reason', $successArray);

        $fallbackDto = RagSynthesisDto::fromArray([
            'answer' => null,
            'citations' => [],
            'successful' => false,
            'fallback_reason' => 'Rate limited',
        ]);

        $this->assertFalse($fallbackDto->successful);
        $this->assertSame('Rate limited', $fallbackDto->fallbackReason);
        $this->assertSame('Rate limited', $fallbackDto->toArray()['fallback_reason']);
    }

    public function testSearchResponseDtoWithSynthesisFixture(): void
    {
        $data = $this->loadFixtureArray('Search/search_response_with_synthesis_200.json');
        /** @var array<string, mixed> $data */
        $response = SearchResponseDto::fromArray($data);

        $this->assertSame(2, $response->total);
        $this->assertCount(2, $response);
        $this->assertTrue($response->hasSynthesis());
        $this->assertNotNull($response->synthesis);
        $this->assertSame(
            'Solar panels produce electricity from sunlight, which inverters then convert for household use.',
            $response->synthesis->answer
        );
        $this->assertSame(['node:101:en', 'node:102:en'], $response->synthesis->citations);

        $array = $response->toArray();
        $this->assertSame(2, $array['total']);
        $this->assertCount(2, $array['results']);
        $this->assertArrayHasKey('synthesis', $array);
    }

    public function testSearchResponseDtoWithoutSynthesisFixture(): void
    {
        $data = $this->loadFixtureArray('Search/search_response_without_synthesis_200.json');
        /** @var array<string, mixed> $data */
        $response = SearchResponseDto::fromArray($data);

        $this->assertSame(2, $response->total);
        $this->assertCount(2, $response);
        $this->assertFalse($response->hasSynthesis());
        $this->assertNull($response->synthesis);

        $array = $response->toArray();
        $this->assertArrayNotHasKey('synthesis', $array);
    }

    public function testSearchResponseDtoCountAndIteration(): void
    {
        $results = [
            new SearchResultItemDto('doc:1', 'Doc 1', 'Snippet 1', 0, 0.9),
            new SearchResultItemDto('doc:2', 'Doc 2', 'Snippet 2', 0, 0.8),
        ];

        $response = new SearchResponseDto($results, 2);

        $this->assertSame(2, $response->count());
        $this->assertCount(2, $response);

        $titles = [];
        foreach ($response as $item) {
            $titles[] = $item->title;
        }

        $this->assertSame(['Doc 1', 'Doc 2'], $titles);
    }

    public function testSearchResponseDtoMalformedPayloadHandling(): void
    {
        $response1 = SearchResponseDto::fromArray(['results' => 'invalid_string']);
        $this->assertCount(0, $response1->results);
        $this->assertSame(0, $response1->total);

        $response2 = SearchResponseDto::fromArray(['hydra:member' => 12345]);
        $this->assertCount(0, $response2->results);
        $this->assertSame(0, $response2->total);
    }
}
