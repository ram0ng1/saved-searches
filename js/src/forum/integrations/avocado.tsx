import { override } from 'flarum/common/extend';

import SaveSearchButton from '../components/SaveSearchButton';

/**
 * O tema ramon/avocado troca a lista de busca do índice por uma página
 * própria, com estado de lista próprio. O botão entra ao lado da ordenação,
 * só na aba de discussões. Sem o Avocado o módulo nunca carrega e nada roda.
 */
export default function addAvocadoSaveSearch() {
  override('ext:ramon/avocado/forum/components/AvocadoSearchPage', 'renderSortDropdown', function (this: any, original: () => unknown) {
    if (this.activeTab !== 'discussions') return original();

    return (
      <div className="SavedSearches-themeActions">
        <SaveSearchButton state={this.discussionsState} />
        {original()}
      </div>
    );
  });
}
