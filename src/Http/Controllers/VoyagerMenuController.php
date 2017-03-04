<?php

namespace TCG\Voyager\Http\Controllers;

use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;

class VoyagerMenuController extends Controller
{
    public function builder($id)
    {
        Voyager::canOrFail('edit_menus');

        $menu = Voyager::model('Menu')->findOrFail($id);

        $isModelTranslatable = isBreadTranslatable(Voyager::model('MenuItem'));

        return view('voyager::menus.builder', compact('menu', 'isModelTranslatable'));
    }

    public function delete_menu($menu, $id)
    {
        Voyager::canOrFail('delete_menus');

        $item = Voyager::model('MenuItem')->findOrFail($id);

        $item->destroy($id);

        return redirect()
            ->route('voyager.menus.builder', [$menu])
            ->with([
                'message'    => 'Successfully Deleted Menu Item.',
                'alert-type' => 'success',
            ]);
    }

    public function add_item(Request $request)
    {
        Voyager::canOrFail('add_menus');

        $data = $this->prepareParameters(
            $request->all()
        );

        $data['order'] = 1;

        $highestOrderMenuItem = Voyager::model('MenuItem')->where('parent_id', '=', null)
            ->orderBy('order', 'DESC')
            ->first();

        if (!is_null($highestOrderMenuItem)) {
            $data['order'] = intval($highestOrderMenuItem->order) + 1;
        }

        // Save menu translations if available
        $data = $this->saveMenuTranslations($menuItem, $data, 'add');

        Voyager::model('MenuItem')->create($data);

        return redirect()
            ->route('voyager.menus.builder', [$data['menu_id']])
            ->with([
                'message'    => 'Successfully Created New Menu Item.',
                'alert-type' => 'success',
            ]);
    }

    public function update_item(Request $request)
    {
        Voyager::canOrFail('edit_menus');

        $id = $request->input('id');
        $data = $this->prepareParameters(
            $request->except(['id'])
        );

        $menuItem = Voyager::model('MenuItem')->findOrFail($id);

        // Save menu translations if available
        $data = $this->saveMenuTranslations($menuItem, $data, 'edit');

        $menuItem->update($data);

        return redirect()
            ->route('voyager.menus.builder', [$menuItem->menu_id])
            ->with([
                'message'    => 'Successfully Updated Menu Item.',
                'alert-type' => 'success',
            ]);
    }

    public function order_item(Request $request)
    {
        $menuItemOrder = json_decode($request->input('order'));

        $this->orderMenu($menuItemOrder, null);
    }

    private function orderMenu(array $menuItems, $parentId)
    {
        foreach ($menuItems as $index => $menuItem) {
            $item = Voyager::model('MenuItem')->findOrFail($menuItem->id);
            $item->order = $index + 1;
            $item->parent_id = $parentId;
            $item->save();

            if (isset($menuItem->children)) {
                $this->orderMenu($menuItem->children, $item->id);
            }
        }
    }

    protected function prepareParameters($parameters)
    {
        switch (array_get($parameters, 'type')) {
            case 'route':
                $parameters['url'] = null;
                break;
            default:
                $parameters['route'] = null;
                $parameters['parameters'] = '';
                break;
        }

        if (isset($parameters['type'])) {
            unset($parameters['type']);
        }

        return $parameters;
    }


    /**
     * Save menu translations
     *
     * @param object $_menuItem
     * @param array  $data     menu data
     * @param string $action   add or edit action
     *
     * @return JSON            translated item
     */
    protected function saveMenuTranslations($_menuItem, array $data, string $action)
    {
        if (isBreadTranslatable($_menuItem)) {
            $key = $action.'_title_i18n';
            $val = json_decode($data[$key], true);

            unset($data[$key]);
            unset($data['i18n_selector']);

            $_menuItem->setAttributeTranslations(
                'title', $val, true
            );
        }

        return $data;
    }
}
