{**
 * Список всех пользователей
 *}

{extends 'layouts/layout.base.tpl'}

{block name='layout_page_title'}{$aLang.user_list}{/block}

{block name='layout_content'}
	{include 'forms/form.search.users.tpl'}
	{include 'components/alphanumeric/alphanumeric.tpl' aAlphaLetters=$aPrefixUser}

	{* Сортировка *}
	{include 'components/sort/sort.ajax.tpl'
		sSortName     = 'sort-user-list'
		sSortSearchType     = 'users'
		aSortList     = [
			[ name => 'user_rating',        text => $aLang.sort.by_rating, order => 'asc'],
			[ name => 'user_login',         text => $aLang.sort.by_login ],
			[ name => 'user_date_register', text => $aLang.sort.by_date_registration ]
		]
	}

	<div class="js-search-ajax-container" data-type="users">
		{include 'components/user_list/user_list.tpl' aUsersList=$aUsers bUseMore=true}
	</div>
{/block}