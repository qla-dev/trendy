<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // get all data from menu.json file
        $verticalMenuJson = file_get_contents(base_path('resources/data/menu-data/mainMenu.json'));
        $verticalMenuData = json_decode($verticalMenuJson);
        $horizontalMenuJson = file_get_contents(base_path('resources/data/menu-data/horizontalMenu.json'));
        $horizontalMenuData = json_decode($horizontalMenuJson);

        // Pre-translate menu items
        $verticalMenuData = $this->translateMenuItems($verticalMenuData);
        $horizontalMenuData = $this->translateMenuItems($horizontalMenuData);

         // Share all menuData to all the views
        \View::share('menuData',[$verticalMenuData, $horizontalMenuData]);
    }

    /**
     * Recursively translate menu items
     */
    private function translateMenuItems($menuData)
    {
        if (isset($menuData->menu)) {
            foreach ($menuData->menu as $menu) {
                if (isset($menu->name)) {
                    $menu->name = __('locale.' . $menu->name);
                }
                if (isset($menu->navheader)) {
                    $menu->navheader = __('locale.' . $menu->navheader);
                }
                if (isset($menu->submenu)) {
                    $this->translateMenuItems((object)['menu' => $menu->submenu]);
                }
            }
        }
        return $menuData;
    }
}
