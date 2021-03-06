<?php

namespace Backend\Modules\Catalog\Domain\Product;

use Backend\Core\Engine\DataGridDatabase;
use Backend\Core\Engine\Authentication as BackendAuthentication;
use Backend\Core\Engine\Model;
use Backend\Core\Language\Language;
use Backend\Core\Language\Locale;
use Backend\Modules\Catalog\Domain\Category\Category;
use Backend\Modules\Catalog\Domain\Category\CategoryRepository;

/**
 * @TODO replace with a doctrine implementation of the data grid
 */
class DataGrid extends DataGridDatabase
{
    public function __construct(Locale $locale, ?Category $category)
    {
        if ($category) {
            parent::__construct(
                'SELECT i.id, i.title AS title, i.sku, i.category_id, i.stock, i.sequence
		 FROM catalog_products AS i
		 WHERE i.language = :language and i.category_id = :category
		 ORDER BY i.sequence ASC',
                ['language' => $locale, 'category' => $category->getId()]
            );
        } else {
            parent::__construct(
                'SELECT i.id, i.title AS title, i.sku, i.category_id, i.stock, i.sequence
		 FROM catalog_products AS i
		 WHERE i.language = :language
		 ORDER BY i.sequence ASC',
                ['language' => $locale]
            );
        }

        $this->enableSequenceByDragAndDrop();
        $this->setAttributes(array('data-action' => 'SequenceProducts'));

        // our JS needs to know an id, so we can highlight it
        $this->setRowAttributes(array('id' => 'row-[id]'));
        $this->setColumnsHidden(array('sequence'));
        $this->setColumnFunction([self::class, 'categoryName'], ['[category_id]'], 'category_id');
        $this->setHeaderLabels(
            [
                'category_id' => ucfirst(Language::lbl('Category')),
                'sku' => ucfirst(Language::lbl('ArticleNumber')),
            ]
        );

        // check if this action is allowed
        if (BackendAuthentication::isAllowedAction('Edit')) {
            $editUrl = Model::createUrlForAction('Edit', null, null,
                ['id' => '[id]', 'category' => $category ? $category->getId() : null], false);
            $this->setColumnURL('title', $editUrl);
            $this->addColumn('edit', null, Language::lbl('Edit'), $editUrl, Language::lbl('Edit'));
        }
    }

    public static function getHtml(Locale $locale, ?Category $category): string
    {
        return (new self($locale, $category))->getContent();
    }

    public static function categoryName(int $categoryId): string
    {
        /**
         * @var CategoryRepository $categoryRepository
         */
        $categoryRepository = Model::get('catalog.repository.category');

        $category = $categoryRepository->findOneByIdAndLocale($categoryId, Locale::workingLocale());

        return self::generateCategoryName($category);
    }

    private static function generateCategoryName(Category $category, $separator = ' - ', $first = true): string
    {
        $name = null;
        if ($category->getParent()) {
            $name = self::generateCategoryName($category->getParent(), $separator,false) . $separator;
        }

        if ($first) {
            $name .= '<strong>' . $category->getTitle() . '</strong>';
        } else {
            $name .= $category->getTitle();
        }

        return $name;
    }
}
