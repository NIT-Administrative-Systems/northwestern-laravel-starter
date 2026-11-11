<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Imports\AddPeopleToRoleImporter;
use Filament\Actions\Imports\ImportDispatcher;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Adds a pasted list of NetIDs and email addresses to a role. The list becomes an import, like
 * an uploaded CSV, and is processed in queued chunks by {@see AddPeopleToRoleImporter}, which
 * gives each person the role through {@see AddPersonToRole}. When it finishes, the person who
 * added them gets a notification saying how many were added, with a list of everyone who
 * wasn't and why.
 */
readonly class AddPeopleToRole
{
    /** The most people one list or file may hold. */
    public const int MAX_PEOPLE = 1000;

    /** People per queued job. Each one may be a Directory Search call. */
    public const int CHUNK_SIZE = 25;

    public function __construct(
        private AddPersonToRole $addPersonToRole,
        private ImportDispatcher $importDispatcher,
    ) {
    }

    /**
     * The NetIDs and email addresses in a pasted list, separated by commas, semicolons, spaces or
     * line breaks, each once.
     *
     * @return list<string>
     */
    public static function parse(string $people): array
    {
        $entries = preg_split('/[\s,;]+/u', $people, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(collect($entries)->unique(fn (string $entry): string => mb_strtolower($entry))->all());
    }

    /**
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function __invoke(Role $role, string $people, User $actor): Import
    {
        $this->addPersonToRole->decide($role, $actor)->authorize();

        $entries = self::parse($people);

        if ($entries === [] || count($entries) > self::MAX_PEOPLE) {
            throw new InvalidArgumentException('Add between 1 and ' . self::MAX_PEOPLE . ' people at a time.');
        }

        $import = new Import();
        $import->user()->associate($actor);
        $import->forceFill([
            'file_name' => 'Pasted list',
            'file_path' => '',
            'importer' => AddPeopleToRoleImporter::class,
            'total_rows' => count($entries),
        ])->save();

        $this->importDispatcher->dispatch(
            import: $import,
            importChunks: array_chunk(array_map(fn (string $entry): array => ['person' => $entry], $entries), self::CHUNK_SIZE),
            columnMap: ['person' => 'person'],
            options: ['role_id' => $role->getKey()],
            job: ImportCsv::class,
            authGuard: 'web',
        );

        return $import;
    }
}
