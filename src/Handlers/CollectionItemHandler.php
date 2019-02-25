<?php

namespace TightenCo\Jigsaw\Handlers;

use Illuminate\Support\Str;
use TightenCo\Jigsaw\File\OutputFile;
use Illuminate\Support\Arr;

class CollectionItemHandler
{
    private $config;
    private $handlers;

    public function __construct($config, $handlers)
    {
        $this->config = $config;
        $this->handlers = collect($handlers);
    }

    public function shouldHandle($file)
    {
        return $this->isInCollectionDirectory($file)
            && ! Str::startsWith($file->getFilename(), ['.', '_']);
    }

    private function isInCollectionDirectory($file)
    {
        $base = $file->topLevelDirectory();

        return Str::startsWith($base, '_') && $this->hasCollectionNamed($this->getCollectionName($file));
    }

    private function hasCollectionNamed($candidate)
    {
        return Arr::get($this->config, 'collections.' . $candidate) !== null;
    }

    private function getCollectionName($file)
    {
        return substr($file->topLevelDirectory(), 1);
    }

    public function handle($file, $pageData)
    {
        $handler = $this->handlers->first(function ($handler) use ($file) {
            return $handler->shouldHandle($file);
        });
        $pageData->setPageVariableToCollectionItem($this->getCollectionName($file), $file->getFilenameWithoutExtension());

        return $handler->handleCollectionItem($file, $pageData)
            ->map(function ($outputFile, $templateToExtend) {
                if ($templateToExtend) {
                    $outputFile->data()->setExtending($templateToExtend);
                }

                $path = $outputFile->data()->page->getPath();

                return new OutputFile(
                    dirname($path),
                    basename($path, '.' . $outputFile->extension()),
                    $outputFile->extension(),
                    $outputFile->contents(),
                    $outputFile->data()
                );
            })->values();
    }
}
