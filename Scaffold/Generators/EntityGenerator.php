<?php namespace Modules\Workshop\Scaffold\Generators;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

class EntityGenerator extends Generator
{
    /**
     * @var \Illuminate\Contracts\Console\Application
     */
    protected $artisan;

    public function __construct(Filesystem $finder, Repository $config)
    {
        parent::__construct($finder, $config);
        $this->artisan = app('Illuminate\Contracts\Console\Application');
    }

    protected $views = [
        'index-view.stub' => 'Resources/views/admin/$ENTITY_NAME$/index.blade',
        'create-view.stub' => 'Resources/views/admin/$ENTITY_NAME$/create.blade',
        'edit-view.stub' => 'Resources/views/admin/$ENTITY_NAME$/edit.blade',
        'create-fields.stub' => 'Resources/views/admin/$ENTITY_NAME$/partials/create-fields.blade',
        'edit-fields.stub' => 'Resources/views/admin/$ENTITY_NAME$/partials/edit-fields.blade',
    ];

    /**
     * Generate the given entities
     *
     * @param array $entities
     */
    public function generate(array $entities)
    {
        $entityType = strtolower($this->entityType);
        $entityTypeStub = "entity-{$entityType}.stub";
        foreach ($entities as $entity) {
            $this->writeFile(
                $this->getModulesPath("Entities/$entity"),
                $this->getContentForStub($entityTypeStub, $entity)
            );
            $this->writeFile(
                $this->getModulesPath("Entities/{$entity}Translation"),
                $this->getContentForStub("{$entityType}-entity-translation.stub", $entity)
            );
            if ($this->entityType == 'Eloquent') {
                $this->generateMigrationsFor($entity);
            }
            $this->generateRepositoriesFor($entity);
            $this->generateControllerFor($entity);
            $this->generateViewsFor($entity);
            $this->generateLanguageFilesFor($entity);
            $this->appendBindingsToServiceProviderFor($entity);
            $this->appendResourceRoutesToRoutesFileFor($entity);
            $this->appendPermissionsFor($entity);
            $this->appendSidebarLinksFor($entity);
        }
    }

    /**
     * Generate the repositories for the given entity
     *
     * @param string $entity
     */
    private function generateRepositoriesFor($entity)
    {
        $entityType = strtolower($this->entityType);
        $this->writeFile(
            $this->getModulesPath("Repositories/{$entity}Repository"),
            $this->getContentForStub('repository-interface.stub', $entity)
        );
        $this->writeFile(
            $this->getModulesPath("Repositories/Cache/Cache{$entity}Decorator"),
            $this->getContentForStub('cache-repository-decorator.stub', $entity)
        );
        $this->writeFile(
            $this->getModulesPath("Repositories/{$this->entityType}/{$this->entityType}{$entity}Repository"),
            $this->getContentForStub("{$entityType}-repository.stub", $entity)
        );
    }

    /**
     * Generate the controller for the given entity
     *
     * @param string $entity
     */
    private function generateControllerFor($entity)
    {
        $this->writeFile(
            $this->getModulesPath("Http/Controllers/Admin/{$entity}Controller"),
            $this->getContentForStub('admin-controller.stub', $entity)
        );
    }

    /**
     * Generate views for the given entity
     *
     * @param string $entity
     */
    private function generateViewsFor($entity)
    {
        $lowerCasePluralEntity = strtolower(str_plural($entity));
        $this->finder->makeDirectory($this->getModulesPath("Resources/views/admin/{$lowerCasePluralEntity}/partials"), 0755, true);

        foreach ($this->views as $stub => $view) {
            $view = str_replace('$ENTITY_NAME$', $lowerCasePluralEntity, $view);
            $this->writeFile(
                $this->getModulesPath($view),
                $this->getContentForStub($stub, $entity)
            );
        }
    }

    /**
     * Generate language files for the given entity
     * @param string $entity
     */
    private function generateLanguageFilesFor($entity)
    {
        $lowerCaseEntity = str_plural(strtolower($entity));
        $this->writeFile(
            $this->getModulesPath("Resources/lang/en/{$lowerCaseEntity}"),
            $this->getContentForStub('lang-entity.stub', $entity)
        );
    }

    /**
     * Generate migrations file for eloquent entities
     *
     * @param string $entity
     */
    private function generateMigrationsFor($entity)
    {
        $lowercasePluralEntityName = strtolower(str_plural($entity));
        $lowercaseModuleName = strtolower($this->name);
        $migrationName = date('Y_m_d_His_')."create_{$lowercaseModuleName}_{$lowercasePluralEntityName}_table";
        $this->writeFile(
            $this->getModulesPath("Database/Migrations/{$migrationName}"),
            $this->getContentForStub('create-table-migration.stub', $entity)
        );

        $lowercaseEntityName = strtolower($entity);
        $migrationName = date('Y_m_d_His_')."create_{$lowercaseModuleName}_{$lowercaseEntityName}_translations_table";
        $this->writeFile(
            $this->getModulesPath("Database/Migrations/{$migrationName}"),
            $this->getContentForStub('create-translation-table-migration.stub', $entity)
        );
    }

    /**
     * Append the IoC bindings for the given entity to the Service Provider
     *
     * @param  string                                       $entity
     * @throws \Illuminate\Filesystem\FileNotFoundException
     */
    private function appendBindingsToServiceProviderFor($entity)
    {
        $moduleProviderContent = $this->finder->get($this->getModulesPath("Providers/{$this->name}ServiceProvider.php"));
        $binding = $this->getContentForStub('bindings.stub', $entity);
        $moduleProviderContent = str_replace('// add bindings', $binding, $moduleProviderContent);
        $this->finder->put($this->getModulesPath("Providers/{$this->name}ServiceProvider.php"), $moduleProviderContent);
    }

    /**
     * Append the routes for the given entity to the routes file
     *
     * @param  string                                       $entity
     * @throws \Illuminate\Filesystem\FileNotFoundException
     */
    private function appendResourceRoutesToRoutesFileFor($entity)
    {
        $routeContent = $this->finder->get($this->getModulesPath('Http/routes.php'));
        $content = $this->getContentForStub('route-resource.stub', $entity);
        $routeContent = str_replace('// append', $content, $routeContent);
        $this->finder->put($this->getModulesPath('Http/routes.php'), $routeContent);
    }

    /**
     * @param  string                                       $entity
     * @throws \Illuminate\Filesystem\FileNotFoundException
     */
    private function appendPermissionsFor($entity)
    {
        $permissionsContent = $this->finder->get($this->getModulesPath('Config/permissions.php'));
        $content = $this->getContentForStub('permissions-append.stub', $entity);
        $permissionsContent = str_replace('// append', $content, $permissionsContent);
        $this->finder->put($this->getModulesPath('Config/permissions.php'), $permissionsContent);
    }

    /**
     * @param string $entity
     */
    private function appendSidebarLinksFor($entity)
    {
        $sidebarComposerContent = $this->finder->get($this->getModulesPath('Composers/SidebarViewComposer.php'));
        $content = $this->getContentForStub('append-sidebar-composer.stub', $entity);
        $sidebarComposerContent = str_replace('// append', $content, $sidebarComposerContent);
        // Append the active state check
        $lowerCasePluralEntity = strtolower(str_plural($entity));
        $lowerCaseModuleName = strtolower($this->name);
        $content = "Request::is(\"*/\$view->prefix/{$lowerCaseModuleName}/{$lowerCasePluralEntity}*\") || '' /* append */";
        $sidebarComposerContent = str_replace("'' /* append */", $content, $sidebarComposerContent);

        $this->finder->put($this->getModulesPath('Composers/SidebarViewComposer.php'), $sidebarComposerContent);
    }
}
