<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests\Dto;

use KinetiStack\Sdk\Dto\ApiKeyDto;
use KinetiStack\Sdk\Dto\ContextHintsDto;
use KinetiStack\Sdk\Dto\DocumentDto;
use KinetiStack\Sdk\Dto\DocumentResponseDto;
use KinetiStack\Sdk\Dto\FacetRequestDto;
use KinetiStack\Sdk\Dto\ImageInputDto;
use KinetiStack\Sdk\Dto\JobDto;
use KinetiStack\Sdk\Dto\ModuleDto;
use KinetiStack\Sdk\Dto\ProjectDto;
use KinetiStack\Sdk\Dto\RagSynthesisDto;
use KinetiStack\Sdk\Dto\RegisterDto;
use KinetiStack\Sdk\Dto\SearchFilterDto;
use KinetiStack\Sdk\Dto\SearchQueryDto;
use KinetiStack\Sdk\Dto\SearchResultItemDto;
use KinetiStack\Sdk\Dto\UserDto;
use KinetiStack\Sdk\Dto\VisionOptionsDto;
use KinetiStack\Sdk\Dto\VisionResponseDto;
use PHPUnit\Framework\TestCase;

class HybridDtoValidationTest extends TestCase
{
    // ==========================================
    // Request DTOs: Fail-Fast InvalidArgumentException
    // ==========================================

    public function testSearchQueryDtoThrowsOnEmptyQuery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty.');
        SearchQueryDto::create('   ');
    }

    public function testSearchQueryDtoThrowsOnInvalidUserRoles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each user role must be a string.');
        SearchQueryDto::create('test')->withUserRoles([['not_a_string']]);
    }

    public function testSearchFilterDtoThrowsOnNonScalarPermission(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission items must be scalar values.');
        (new SearchFilterDto())->withPermissions(['valid', ['nested_array']]);
    }

    public function testFacetRequestDtoIgnoresNonScalarFilterValue(): void
    {
        $dto = (new FacetRequestDto())->withFilter('field', ['scalar', ['not_scalar']]);
        $this->assertSame(['scalar'], $dto->filters['field']);
    }

    public function testContextHintsDtoThrowsOnNonStringTaxonomy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Taxonomy items must be scalar values.');
        ContextHintsDto::create()->withTaxonomy(['valid', []]);
    }

    public function testVisionOptionsDtoThrowsOnNegativeMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxLength must be at least 1.');
        VisionOptionsDto::create()->withMaxLength(-1);
    }

    public function testImageInputDtoThrowsOnEmptyExternalId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');
        new ImageInputDto('   ', 'https://example.com/img.jpg');
    }

    public function testImageInputDtoThrowsWhenNeitherUrlNorBase64Provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one of imageUrl or imageBase64 must be provided.');
        new ImageInputDto('img-1', null, null, null);
    }

    public function testRegisterDtoThrowsOnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email cannot be empty.');
        new RegisterDto('Valid Org', '   ');
    }

    public function testDocumentDtoThrowsOnEmptyExternalId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('externalId cannot be empty.');
        new DocumentDto('   ', 'title', 'content');
    }

    public function testDocumentDtoThrowsOnEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('title cannot be empty.');
        new DocumentDto('id-1', '   ', 'content');
    }

    // ==========================================
    // Response DTOs: Resilient scalar coercion and fallback
    // ==========================================

    public function testVisionResponseDtoResilientFallbackOnNonScalarValues(): void
    {
        $data = [
            'alt_text' => ['unexpected_array'],
            'caption' => null,
            'tags' => 'not_an_array',
            'confidence_score' => 'invalid_number',
            'model_used' => ['unexpected_object'],
            'tokens_consumed' => ['not_int'],
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame('', $dto->altText);
        $this->assertNull($dto->caption);
        $this->assertSame([], $dto->tags);
        $this->assertNull($dto->confidenceScore);
        $this->assertNull($dto->modelUsed);
        $this->assertNull($dto->tokensConsumed);
    }

    public function testVisionResponseDtoCoercesScalarsGracefully(): void
    {
        $data = [
            'alt_text' => 12345,
            'caption' => 67890,
            'tags' => ['tag1', 99, true],
            'confidence_score' => '0.85',
            'model_used' => 'test-model',
            'tokens_consumed' => '42',
        ];

        $dto = VisionResponseDto::fromArray($data);

        $this->assertSame('12345', $dto->altText);
        $this->assertSame('67890', $dto->caption);
        $this->assertSame(['tag1', '99', '1'], $dto->tags);
        $this->assertSame(0.85, $dto->confidenceScore);
        $this->assertSame('test-model', $dto->modelUsed);
        $this->assertSame(42, $dto->tokensConsumed);
    }

    public function testJobDtoResilientFallbackOnNonScalarValues(): void
    {
        $data = [
            'job_id' => 'job-123',
            'type' => ['unexpected_array'],
            'status' => 'completed',
            'total_images' => 'invalid_int',
            'processed_images' => ['not_int'],
            'chunks_generated' => ['not_int'],
            'poll_url' => ['unexpected_url'],
            'tokens_consumed' => ['nested_error'],
        ];

        $dto = JobDto::fromArray($data);

        $this->assertSame('job-123', $dto->jobId);
        $this->assertNull($dto->type);
        $this->assertNull($dto->totalImages);
        $this->assertNull($dto->processedImages);
        $this->assertNull($dto->chunksGenerated);
        $this->assertNull($dto->pollUrl);
        $this->assertNull($dto->tokensConsumed);
    }

    public function testDocumentResponseDtoResilientFallback(): void
    {
        $data = [
            'document_id' => 'doc-uuid-1',
            'external_id' => ['not_string'],
            'status' => 'pending',
            'chunks_generated' => 'not_a_number',
            'tokens_consumed' => ['not_int'],
        ];

        $dto = DocumentResponseDto::fromArray($data);

        $this->assertSame('doc-uuid-1', $dto->documentId);
        $this->assertSame('', $dto->externalId);
        $this->assertSame(0, $dto->chunksGenerated);
        $this->assertSame('pending', $dto->status);
        $this->assertNull($dto->tokensConsumed);
    }

    public function testSearchResultItemDtoResilientFallback(): void
    {
        $data = [
            'external_id' => 'item-1',
            'title' => ['array_title'],
            'content' => ['array_content'],
            'chunk_index' => 'not_int',
            'score' => 'not_float',
            'metadata' => 'not_an_array',
        ];

        $dto = SearchResultItemDto::fromArray($data);

        $this->assertSame('item-1', $dto->externalId);
        $this->assertSame('', $dto->title);
        $this->assertSame('', $dto->content);
        $this->assertSame(0, $dto->chunkIndex);
        $this->assertSame(0.0, $dto->score);
        $this->assertNull($dto->metadata);
    }

    public function testRagSynthesisDtoResilientFallback(): void
    {
        $data = [
            'answer' => ['array_answer'],
            'citations' => 'not_an_array',
            'successful' => ['not_a_bool'],
            'fallback_reason' => ['array_reason'],
            'tokens_consumed' => ['not_int'],
        ];

        $dto = RagSynthesisDto::fromArray($data);

        $this->assertNull($dto->answer);
        $this->assertSame([], $dto->citations);
        $this->assertTrue($dto->successful);
        $this->assertNull($dto->fallbackReason);
        $this->assertNull($dto->tokensConsumed);
    }

    public function testProjectDtoResilientFallback(): void
    {
        $data = [
            'id' => 'proj-1',
            'name' => 12345, // coerced to string
            'domain' => 'example.com',
            'created_at' => ['not_string'], // falls back to null
            'monthly_quota_cap' => 'not_int', // falls back to null
        ];

        $dto = ProjectDto::fromArray($data);

        $this->assertSame('proj-1', $dto->id);
        $this->assertSame('12345', $dto->name);
        $this->assertSame('example.com', $dto->domain);
        $this->assertNull($dto->createdAt);
        $this->assertNull($dto->monthlyQuotaCap);
    }

    public function testApiKeyDtoResilientFallback(): void
    {
        $data = [
            'id' => 'key-1',
            'name' => 'Test Key',
            'token_suffix' => 'abcd',
            'scope' => ['not_string'],
            'rate_limit_per_minute' => 'not_int',
            'daily_token_quota_override' => 'not_int',
        ];

        $dto = ApiKeyDto::fromArray($data);

        $this->assertSame('key-1', $dto->id);
        $this->assertSame('all', $dto->scope);
        $this->assertNull($dto->rateLimitPerMinute);
        $this->assertNull($dto->dailyTokenQuotaOverride);
    }

    public function testUserDtoResilientFallback(): void
    {
        $data = [
            'id' => 'usr-1',
            'email' => 'user@example.com',
            'roles' => ['admin', 123, ['nested_array']], // extracts scalars, ignores nested array
            'must_change_password' => 'truthy_string',
            'is_verified' => 1,
        ];

        $dto = UserDto::fromArray($data);

        $this->assertSame('usr-1', $dto->id);
        $this->assertSame('user@example.com', $dto->email);
        $this->assertSame(['admin', '123'], $dto->roles);
        $this->assertTrue($dto->mustChangePassword);
        $this->assertTrue($dto->isVerified);
    }

    public function testModuleDtoResilientFallback(): void
    {
        $data = [
            'identifier' => 'module-1',
            'label' => 12345, // coerced
            'status' => ['invalid_array'], // empty string
            'accessible' => ['not_a_bool'], // false
        ];

        $dto = ModuleDto::fromArray($data);

        $this->assertSame('module-1', $dto->identifier);
        $this->assertSame('12345', $dto->label);
        $this->assertSame('', $dto->status);
        $this->assertFalse($dto->accessible);
    }

    public function testBooleanStringCoercionHandlesFalsyStrings(): void
    {
        foreach (['false', '0', 'off', 'no', 'FALSE', 'OFF', 'NO'] as $falsy) {
            $dto = ModuleDto::fromArray([
                'identifier' => 'mod',
                'label' => 'Mod',
                'status' => 'active',
                'accessible' => $falsy,
            ]);
            $this->assertFalse($dto->accessible, "Expected string '{$falsy}' to coerce to false.");
        }
    }

    public function testBooleanStringCoercionHandlesTruthyStrings(): void
    {
        foreach (['true', '1', 'on', 'yes', 'TRUE', 'ON', 'YES'] as $truthy) {
            $dto = ModuleDto::fromArray([
                'identifier' => 'mod',
                'label' => 'Mod',
                'status' => 'active',
                'accessible' => $truthy,
            ]);
            $this->assertTrue($dto->accessible, "Expected string '{$truthy}' to coerce to true.");
        }
    }
}
