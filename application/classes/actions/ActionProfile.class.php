<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * Экшен обработки профайла юзера, т.е. УРЛ вида /profile/login/
 *
 * @package actions
 * @since 1.0
 */
class ActionProfile extends Action {
	/**
	 * Объект юзера чей профиль мы смотрим
	 *
	 * @var ModuleUser_EntityUser|null
	 */
	protected $oUserProfile;
	/**
	 * Главное меню
	 *
	 * @var string
	 */
	protected $sMenuHeadItemSelect='people';
	/**
	 * Субменю
	 *
	 * @var string
	 */
	protected $sMenuSubItemSelect='';
	/**
	 * Текущий пользователь
	 *
	 * @var ModuleUser_EntityUser|null
	 */
	protected $oUserCurrent;

	/**
	 * Инициализация
	 */
	public function Init() {
		$this->oUserCurrent=$this->User_GetUserCurrent();
	}
	/**
	 * Регистрация евентов
	 */
	protected function RegisterEvent() {
		$this->AddEvent('friendoffer','EventFriendOffer');
		$this->AddEvent('ajaxfriendadd', 'EventAjaxFriendAdd');
		$this->AddEvent('ajaxfrienddelete', 'EventAjaxFriendDelete');
		$this->AddEvent('ajaxfriendaccept', 'EventAjaxFriendAccept');
		$this->AddEvent('ajax-note-save', 'EventAjaxNoteSave');
		$this->AddEvent('ajax-note-remove', 'EventAjaxNoteRemove');
		$this->AddEvent('ajax-modal-complaint', 'EventAjaxModalComplaint');
		$this->AddEvent('ajax-complaint-add', 'EventAjaxComplaintAdd');

		$this->AddEventPreg('/^.+$/i','/^(whois)?$/i','EventWhois');

		$this->AddEventPreg('/^.+$/i','/^wall$/i','/^$/i','EventWall');
		$this->AddEventPreg('/^.+$/i','/^wall$/i','/^add$/i','EventWallAdd');
		$this->AddEventPreg('/^.+$/i','/^wall$/i','/^load$/i','EventWallLoad');
		$this->AddEventPreg('/^.+$/i','/^wall$/i','/^load-reply$/i','EventWallLoadReply');
		$this->AddEventPreg('/^.+$/i','/^wall$/i','/^remove$/i','EventWallRemove');

		$this->AddEventPreg('/^.+$/i','/^favourites$/i','/^comments$/i','/^(page([1-9]\d{0,5}))?$/i','EventFavouriteComments');
		$this->AddEventPreg('/^.+$/i','/^favourites$/i','/^(page([1-9]\d{0,5}))?$/i','EventFavourite');
		$this->AddEventPreg('/^.+$/i','/^favourites$/i','/^topics/i','/^(page([1-9]\d{0,5}))?$/i','EventFavourite');
		$this->AddEventPreg('/^.+$/i','/^favourites$/i','/^topics/i','/^tag/i','/^.+/i','/^(page([1-9]\d{0,5}))?$/i','EventFavouriteTopicsTag');

		$this->AddEventPreg('/^.+$/i','/^created/i','/^notes/i','/^(page([1-9]\d{0,5}))?$/i','EventCreatedNotes');
		$this->AddEventPreg('/^.+$/i','/^created/i','/^(page([1-9]\d{0,5}))?$/i','EventCreatedTopics');
		$this->AddEventPreg('/^.+$/i','/^created/i','/^topics/i','/^(page([1-9]\d{0,5}))?$/i','EventCreatedTopics');
		$this->AddEventPreg('/^.+$/i','/^created/i','/^comments$/i','/^(page([1-9]\d{0,5}))?$/i','EventCreatedComments');

		$this->AddEventPreg('/^.+$/i','/^friends/i','/^(page([1-9]\d{0,5}))?$/i','EventFriends');
		$this->AddEventPreg('/^.+$/i','/^stream/i','/^$/i','EventStream');

		$this->AddEventPreg('/^changemail$/i','/^confirm-from/i','/^\w{32}$/i','EventChangemailConfirmFrom');
		$this->AddEventPreg('/^changemail$/i','/^confirm-to/i','/^\w{32}$/i','EventChangemailConfirmTo');
	}

	/**********************************************************************************
	 ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
	 **********************************************************************************
	 */

	/**
	 * Проверка корректности профиля
	 */
	protected function CheckUserProfile() {
		/**
		 * Проверяем есть ли такой юзер
		 */
		if (!($this->oUserProfile=$this->User_GetUserByLogin($this->sCurrentEvent))) {
			return false;
		}
		return true;
	}
	/**
	 * Показывает модальное окно для жалобы
	 */
	protected function EventAjaxModalComplaint() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		if (!$this->oUserCurrent) {
			return parent::EventNotFound();
		}

		$oViewer=$this->Viewer_GetLocalViewer();
		$this->Viewer_AssignAjax('sText',$oViewer->Fetch("modals/modal.complaint_user.tpl"));
	}
	/**
	 * Показывает модальное окно для жалобы
	 */
	protected function EventAjaxComplaintAdd() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		if (!$this->oUserCurrent) {
			return parent::EventNotFound();
		}
		/**
		 * Создаем жалобу и проводим валидацию
		 */
		$oComplaint=Engine::GetEntity('ModuleUser_EntityComplaint');
		$oComplaint->setTargetUserId(getRequestStr('user_id'));
		$oComplaint->setUserId($this->oUserCurrent->getId());
		$oComplaint->setText(getRequestStr('text'));
		$oComplaint->setType(getRequestStr('type'));
		$oComplaint->setCaptcha(getRequestStr('captcha'));
		$oComplaint->setState(ModuleUser::COMPLAINT_STATE_NEW);

		if ($oComplaint->_Validate()) {
			/**
			 * Экранируем текст и добавляем запись в БД
			 */
			$oComplaint->setText(htmlspecialchars($oComplaint->getText()));
			if ($this->User_AddComplaint($oComplaint)) {
				$this->Message_AddNotice($this->Lang_Get('user_complaint_submit_result'),$this->Lang_Get('attention'));
				/**
				 * Убиваем каптчу
				 */
				unset($_SESSION['captcha_keystring_complaint_user']);
				/**
				 * Отправляем уведомление админу
				 */
				if (Config::Get('module.user.complaint_notify_by_mail')) {
					$this->Notify_SendUserComplaint($oComplaint);
				}
				return true;
			} else {
				$this->Message_AddError($this->Lang_Get('common.error.save'),$this->Lang_Get('error'));
			}
		} else {
			$this->Message_AddError($oComplaint->_getValidateError(),$this->Lang_Get('error'));
		}
	}
	/**
	 * Чтение активности пользователя (stream)
	 */
	protected function EventStream() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		/**
		 * Читаем события
		 */
		$aEvents = $this->Stream_ReadByUserId($this->oUserProfile->getId());
		$this->Viewer_Assign('bDisableGetMoreButton', $this->Stream_GetCountByUserId($this->oUserProfile->getId()) < Config::Get('module.stream.count_default'));
		$this->Viewer_Assign('aStreamEvents', $aEvents);
		if (count($aEvents)) {
			$oEvenLast=end($aEvents);
			$this->Viewer_Assign('iStreamLastId', $oEvenLast->getId());
			$this->Viewer_Assign('sDateLast', $oEvenLast->getDateAdded());
		}
		$this->SetTemplateAction('activity');
	}
	/**
	 * Список друзей пользователей
	 */
	protected function EventFriends() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(1,2) ? $this->GetParamEventMatch(1,2) : 1;
		/**
		 * Получаем список комментов
		 */
		$aResult=$this->User_GetUsersFriend($this->oUserProfile->getId(),$iPage,Config::Get('module.user.per_page'));
		$aFriends=$aResult['collection'];
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.user.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'friends');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aFriends',$aFriends);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_friends').' '.$this->oUserProfile->getLogin());

		$this->SetTemplateAction('friends');
	}
	/**
	 * Список топиков пользователя
	 */
	protected function EventCreatedTopics() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='topics';
		/**
		 * Передан ли номер страницы
		 */
		if ($this->GetParamEventMatch(1,0)=='topics') {
			$iPage=$this->GetParamEventMatch(2,2) ? $this->GetParamEventMatch(2,2) : 1;
		} else {
			$iPage=$this->GetParamEventMatch(1,2) ? $this->GetParamEventMatch(1,2) : 1;
		}
		/**
		 * Получаем список топиков
		 */
		$aResult=$this->Topic_GetTopicsPersonalByUser($this->oUserProfile->getId(),1,$iPage,Config::Get('module.topic.per_page'));
		$aTopics=$aResult['collection'];
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('topics_list_show',array('aTopics'=>$aTopics));
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'created/topics');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aTopics',$aTopics);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_publication').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_publication_blog'));
		$this->Viewer_SetHtmlRssAlternate(Router::GetPath('rss').'personal_blog/'.$this->oUserProfile->getLogin().'/',$this->oUserProfile->getLogin());
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('created.topics');
	}
	/**
	 * Вывод комментариев пользователя
	 */
	protected function EventCreatedComments() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='comments';
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(2,2) ? $this->GetParamEventMatch(2,2) : 1;
		/**
		 * Получаем список комментов
		 */
		$aResult=$this->Comment_GetCommentsByUserId($this->oUserProfile->getId(),'topic',$iPage,Config::Get('module.comment.per_page'));
		$aComments=$aResult['collection'];
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.comment.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'created/comments');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aComments',$aComments);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_publication').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_publication_comment'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('created.comments');
	}
	/**
	 * Выводит список избранноего юзера
	 *
	 */
	protected function EventFavourite() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='topics';
		/**
		 * Передан ли номер страницы
		 */
		if ($this->GetParamEventMatch(1,0)=='topics') {
			$iPage=$this->GetParamEventMatch(2,2) ? $this->GetParamEventMatch(2,2) : 1;
		} else {
			$iPage=$this->GetParamEventMatch(1,2) ? $this->GetParamEventMatch(1,2) : 1;
		}
		/**
		 * Получаем список избранных топиков
		 */
		$aResult=$this->Topic_GetTopicsFavouriteByUserId($this->oUserProfile->getId(),$iPage,Config::Get('module.topic.per_page'));
		$aTopics=$aResult['collection'];
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('topics_list_show',array('aTopics'=>$aTopics));
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'favourites/topics');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aTopics',$aTopics);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_favourites'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('favourite.topics');
	}
	/**
	 * Список топиков из избранного по тегу
	 */
	protected function EventFavouriteTopicsTag() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		/**
		 * Пользователь авторизован и просматривает свой профиль?
		 */
		if (!$this->oUserCurrent or $this->oUserProfile->getId()!=$this->oUserCurrent->getId()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='topics';
		$sTag=$this->GetParamEventMatch(3,0);
		/*
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(4,2) ? $this->GetParamEventMatch(4,2) : 1;
		/**
		 * Получаем список избранных топиков
		 */
		$aResult=$this->Favourite_GetTags(array('target_type'=>'topic','user_id'=>$this->oUserProfile->getId(),'text'=>$sTag),array('target_id'=>'desc'),$iPage,Config::Get('module.topic.per_page'));
		$aTopicId=array();
		foreach($aResult['collection'] as $oTag) {
			$aTopicId[]=$oTag->getTargetId();
		}
		$aTopics=$this->Topic_GetTopicsAdditionalData($aTopicId);
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.topic.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'favourites/topics/tag/'.htmlspecialchars($sTag));
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aTopics',$aTopics);
		$this->Viewer_Assign('sFavouriteTag',htmlspecialchars($sTag));
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_favourites'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('favourite.topics');
	}
	/**
	 * Выводит список избранноего юзера
	 *
	 */
	protected function EventFavouriteComments() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='comments';
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(2,2) ? $this->GetParamEventMatch(2,2) : 1;
		/**
		 * Получаем список избранных комментариев
		 */
		$aResult=$this->Comment_GetCommentsFavouriteByUserId($this->oUserProfile->getId(),$iPage,Config::Get('module.comment.per_page'));
		$aComments=$aResult['collection'];
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.comment.per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'favourites/comments');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aComments',$aComments);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_favourites_comments'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('favourite.comments');
	}
	/**
	 * Показывает инфу профиля
	 *
	 */
	protected function EventWhois() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='main';
		/**
		 * Получаем список друзей
		 */
		$aUsersFriend=$this->User_GetUsersFriend($this->oUserProfile->getId(),1,Config::Get('module.user.friend_on_profile'));
		/**
		 * Если активен режим инвайтов, то прогружаем дополнительную информацию
		 */
		if (Config::Get('general.reg.invite')) {
			/**
			 * Получаем список тех кого пригласил юзер
			 */
			$aUsersInvite=$this->User_GetUsersInvite($this->oUserProfile->getId());
			$this->Viewer_Assign('aUsersInvite',$aUsersInvite);
			/**
			 * Получаем того юзера, кто пригласил текущего
			 */
			$oUserInviteFrom=$this->User_GetUserInviteFrom($this->oUserProfile->getId());
			$this->Viewer_Assign('oUserInviteFrom',$oUserInviteFrom);
		}
		/**
		 * Получаем список юзеров блога
		 */
		$aBlogUsers=$this->Blog_GetBlogUsersByUserId($this->oUserProfile->getId(),ModuleBlog::BLOG_USER_ROLE_USER);
		$aBlogModerators=$this->Blog_GetBlogUsersByUserId($this->oUserProfile->getId(),ModuleBlog::BLOG_USER_ROLE_MODERATOR);
		$aBlogAdministrators=$this->Blog_GetBlogUsersByUserId($this->oUserProfile->getId(),ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
		/**
		 * Получаем список блогов которые создал юзер
		 */
		$aBlogsOwner=$this->Blog_GetBlogsByOwnerId($this->oUserProfile->getId());
		/**
		 * Получаем список контактов
		 */
		$aUserFields = $this->User_getUserFieldsValues($this->oUserProfile->getId());
		/**
		 * Вызов хуков
		 */
		$this->Hook_Run('profile_whois_show',array("oUserProfile"=>$this->oUserProfile));
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aBlogUsers',$aBlogUsers);
		$this->Viewer_Assign('aBlogModerators',$aBlogModerators);
		$this->Viewer_Assign('aBlogAdministrators',$aBlogAdministrators);
		$this->Viewer_Assign('aBlogsOwner',$aBlogsOwner);
		$this->Viewer_Assign('aUsersFriend',$aUsersFriend['collection']);
		$this->Viewer_Assign('aUserFields',$aUserFields);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_whois'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('info');
	}
	/**
	 * Отображение стены пользователя
	 */
	public function EventWall() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		/**
		 * Получаем записи стены
		 */

		$aWall = $this->Wall_GetWall(array('wall_user_id'=>$this->oUserProfile->getId(),'pid'=>null),array('id'=>'desc'),1,Config::Get('module.wall.per_page'));
		$aPosts = $aWall['collection'];

		$this->Viewer_Assign('aWall', $aPosts);
		$this->Viewer_Assign('iCountWall',$aWall['count']);

		if (count($aPosts)) {
			$oPostLast = end($aPosts);
			$this->Viewer_Assign('iWallLastId', $oPostLast->getId());
		}
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('wall');
	}
	/**
	 * Добавление записи на стену
	 */
	public function EventWallAdd() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			return $this->EventErrorDebug();
		}
		if (!$this->CheckUserProfile()) {
			return $this->EventErrorDebug();
		}
		/**
		 * Создаем запись
		 */
		$oWall=Engine::GetEntity('Wall');
		$oWall->_setValidateScenario('add');
		$oWall->setWallUserId($this->oUserProfile->getId());
		$oWall->setUserId($this->oUserCurrent->getId());
		$oWall->setText(getRequestStr('sText'));
		$oWall->setPid(getRequestStr('iPid'));

		$this->Hook_Run('wall_add_validate_before', array('oWall'=>$oWall));
		if ($oWall->_Validate()) {
			/**
			 * Экранируем текст и добавляем запись в БД
			 */
			$oWall->setText($this->Text_Parser($oWall->getText()));
			$this->Hook_Run('wall_add_before', array('oWall'=>$oWall));
			if ($this->Wall_AddWall($oWall)) {
				$this->Hook_Run('wall_add_after', array('oWall'=>$oWall));
				/**
				 * Отправляем уведомления
				 */
				if ($oWall->getWallUserId()!=$oWall->getUserId()) {
					$this->Notify_SendWallNew($oWall,$this->oUserCurrent);
				}
				if ($oWallParent=$oWall->GetPidWall() and $oWallParent->getUserId()!=$oWall->getUserId()) {
					$this->Notify_SendWallReply($oWallParent,$oWall,$this->oUserCurrent);
				}
				/**
				 * Добавляем событие в ленту
				 */
				$this->Stream_Write($oWall->getUserId(), 'add_wall', $oWall->getId());
			} else {
				$this->Message_AddError($this->Lang_Get('wall_add_error'),$this->Lang_Get('error'));
			}
		} else {
			$this->Message_AddError($oWall->_getValidateError(),$this->Lang_Get('error'));
		}
	}
	/**
	 * Удаление записи со стены
	 */
	public function EventWallRemove() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			return $this->EventErrorDebug();
		}
		if (!$this->CheckUserProfile()) {
			return $this->EventErrorDebug();
		}
		/**
		 * Получаем запись
		 */
		if (!($oWall=$this->Wall_GetWallById(getRequestStr('iId')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Если разрешено удаление - удаляем
		 */
		if ($oWall->isAllowDelete()) {
			$this->Wall_DeleteWall($oWall);
			return;
		}
		return $this->EventErrorDebug();
	}

	/**
	 * Ajax подгрузка сообщений стены
	 */
	public function EventWallLoad() {
		// Устанавливаем формат Ajax ответа
		$this->Viewer_SetResponseAjax('json');

		// Валидация
		if ( ! $this->CheckUserProfile() ) {
			return $this->EventErrorDebug();
		}

		// Формируем фильтр для запроса к БД
		$aFilter = array(
			'wall_user_id' => $this->oUserProfile->getId(),
			'pid'          => null
		);

		if ( is_numeric(getRequest('iLastId')) ) {
			$aFilter['id_less'] = getRequest('iLastId');
		} else if ( is_numeric(getRequest('iFirstId')) ) {
			$aFilter['id_more'] = getRequest('iFirstId');
		} else {
			return $this->EventErrorDebug();
		}

		// Получаем сообщения и формируем ответ
		$aWall = $this->Wall_GetWall($aFilter, array('id' => 'desc'), 1, Config::Get('module.wall.per_page'));

		$this->Viewer_Assign('aWall', $aWall['collection']);
		$this->Viewer_Assign('oUserCurrent', $this->oUserCurrent); // хак, т.к. к этому моменту текущий юзер не загружен в шаблон

		$this->Viewer_AssignAjax('sHtml', $this->Viewer_Fetch('actions/ActionProfile/wall.posts.tpl'));
		$this->Viewer_AssignAjax('iCountLoaded', count($aWall['collection']));

		if (count($aWall['collection'])) {
			$this->Viewer_AssignAjax('iLastId', end($aWall['collection'])->getId());
		}
	}

	/**
	 * Подгрузка ответов на стене к сообщению
	 */
	public function EventWallLoadReply() {
		// Устанавливаем формат Ajax ответа
		$this->Viewer_SetResponseAjax('json');

		// Валидация
		if ( ! $this->CheckUserProfile() ) {
			return $this->EventErrorDebug();
		}

		if ( ! ($oWall = $this->Wall_GetWallById(getRequestStr('iTargetId'))) or $oWall->getPid() ) {
			return $this->EventErrorDebug();
		}

		// Формируем фильтр для запроса к БД
		$aFilter = array(
			'wall_user_id' => $this->oUserProfile->getId(),
			'pid'          => $oWall->getId()
		);

		if ( is_numeric(getRequest('iLastId')) ) {
			$aFilter['id_less'] = getRequest('iLastId');
		} else if ( is_numeric(getRequest('iFirstId')) ) {
			$aFilter['id_more'] = getRequest('iFirstId');
		} else {
			return $this->EventErrorDebug();
		}

		// Получаем сообщения и формируем ответ
		// Необходимо вернуть все ответы, но ставим "разумное" ограничение
		$aWall = $this->Wall_GetWall($aFilter, array('id' => 'asc'), 1, 300);

		// Передаем переменные
		$this->Viewer_Assign('aReplyWall', $aWall['collection']);

		$this->Viewer_AssignAjax('sHtml', $this->Viewer_Fetch('actions/ActionProfile/wall.comments.tpl'));
		$this->Viewer_AssignAjax('iCountLoaded', count($aWall['collection']));

		if (count($aWall['collection'])) {
			$this->Viewer_AssignAjax('iLastId', end($aWall['collection'])->getId());
		}
	}

	/**
	 * Сохраняет заметку о пользователе
	 */
	public function EventAjaxNoteSave() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			return $this->EventErrorDebug();
		}
		/**
		 * Создаем заметку и проводим валидацию
		 */
		$oNote=Engine::GetEntity('ModuleUser_EntityNote');
		$oNote->setTargetUserId(getRequestStr('iUserId'));
		$oNote->setUserId($this->oUserCurrent->getId());
		$oNote->setText(getRequestStr('text'));

		if ($oNote->_Validate()) {
			/**
			 * Экранируем текст и добавляем запись в БД
			 */
			$oNote->setText(htmlspecialchars(strip_tags($oNote->getText())));
			if ($this->User_SaveNote($oNote)) {
				$this->Viewer_AssignAjax('sText',$oNote->getText());
			} else {
				$this->Message_AddError($this->Lang_Get('common.error.save'),$this->Lang_Get('error'));
			}
		} else {
			$this->Message_AddError($oNote->_getValidateError(),$this->Lang_Get('error'));
		}
	}
	/**
	 * Удаляет заметку о пользователе
	 */
	public function EventAjaxNoteRemove() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		if (!$this->oUserCurrent) {
			return $this->EventErrorDebug();
		}

		if (!($oUserTarget=$this->User_GetUserById(getRequestStr('iUserId')))) {
			return $this->EventErrorDebug();
		}
		if (!($oNote=$this->User_GetUserNote($oUserTarget->getId(),$this->oUserCurrent->getId()))) {
			return $this->EventErrorDebug();
		}
		$this->User_DeleteUserNoteById($oNote->getId());
	}
	/**
	 * Список созданных заметок
	 */
	public function EventCreatedNotes() {
		if (!$this->CheckUserProfile()) {
			return parent::EventNotFound();
		}
		$this->sMenuSubItemSelect='notes';
		/**
		 * Заметки может читать только сам пользователь
		 */
		if (!$this->oUserCurrent or $this->oUserCurrent->getId()!=$this->oUserProfile->getId()) {
			return parent::EventNotFound();
		}
		/**
		 * Передан ли номер страницы
		 */
		$iPage=$this->GetParamEventMatch(2,2) ? $this->GetParamEventMatch(2,2) : 1;
		/**
		 * Получаем список заметок
		 */
		$aResult=$this->User_GetUsersByNoteAndUserId($this->oUserProfile->getId(),$iPage,Config::Get('module.user.usernote_per_page'));
		$aNotes=$aResult['collection'];
		/**
		 * Формируем постраничность
		 */
		$aPaging=$this->Viewer_MakePaging($aResult['count'],$iPage,Config::Get('module.user.usernote_per_page'),Config::Get('pagination.pages.count'),$this->oUserProfile->getUserWebPath().'created/notes');
		/**
		 * Загружаем переменные в шаблон
		 */
		$this->Viewer_Assign('aPaging',$aPaging);
		$this->Viewer_Assign('aUsersList',$aNotes);
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile').' '.$this->oUserProfile->getLogin());
		$this->Viewer_AddHtmlTitle($this->Lang_Get('user_menu_profile_notes'));
		/**
		 * Устанавливаем шаблон вывода
		 */
		$this->SetTemplateAction('created.notes');
	}
	/**
	 * Добавление пользователя в друзья, по отправленной заявке
	 */
	public function EventFriendOffer() {
		require_once Config::Get('path.framework.libs_vendor.server').'/XXTEA/encrypt.php';
		/**
		 * Из реквеста дешефруем ID польователя
		 */
		$sUserId=xxtea_decrypt(base64_decode(rawurldecode(getRequestStr('code'))), Config::Get('module.talk.encrypt'));
		if (!$sUserId) {
			return $this->EventNotFound();
		}
		list($sUserId,)=explode('_',$sUserId,2);

		$sAction=$this->GetParam(0);
		/**
		 * Получаем текущего пользователя
		 */
		if(!$this->User_IsAuthorization()) {
			return $this->EventNotFound();
		}
		$this->oUserCurrent = $this->User_GetUserCurrent();
		/**
		 * Получаем объект пользователя приславшего заявку,
		 * если пользователь не найден, переводим в раздел сообщений (Talk) -
		 * так как пользователь мог перейти сюда либо из talk-сообщений,
		 * либо из e-mail письма-уведомления
		 */
		if(!$oUser=$this->User_GetUserById($sUserId)) {
			$this->Message_AddError($this->Lang_Get('user_not_found'),$this->Lang_Get('error'),true);
			Router::Location(Router::GetPath('talk'));
			return ;
		}
		/**
		 * Получаем связь дружбы из базы данных.
		 * Если связь не найдена либо статус отличен от OFFER,
		 * переходим в раздел Talk и возвращаем сообщение об ошибке
		 */
		$oFriend=$this->User_GetFriend($this->oUserCurrent->getId(),$oUser->getId(),0);
		if(!$oFriend
			|| !in_array(
				$oFriend->getFriendStatus(),
				array(
					ModuleUser::USER_FRIEND_OFFER+ModuleUser::USER_FRIEND_NULL,
				)
			)
		) {
			$sMessage=($oFriend)
				? $this->Lang_Get('user_friend_offer_already_done')
				: $this->Lang_Get('user_friend_offer_not_found');
			$this->Message_AddError($sMessage,$this->Lang_Get('error'),true);

			Router::Location(Router::GetPath('talk'));
			return ;
		}
		/**
		 * Устанавливаем новый статус связи
		 */
		$oFriend->setStatusTo(
			($sAction=='accept')
				? ModuleUser::USER_FRIEND_ACCEPT
				: ModuleUser::USER_FRIEND_REJECT
		);

		if ($this->User_UpdateFriend($oFriend)) {
			$sMessage=($sAction=='accept')
				? $this->Lang_Get('user_friend_add_ok')
				: $this->Lang_Get('user_friend_offer_reject');

			$this->Message_AddNoticeSingle($sMessage,$this->Lang_Get('attention'),true);
			$this->NoticeFriendOffer($oUser,$sAction);
		} else {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('system_error'),
				$this->Lang_Get('error'),
				true
			);
		}
		Router::Location(Router::GetPath('talk'));
	}
	/**
	 * Подтверждение заявки на добавления в друзья
	 */
	public function EventAjaxFriendAccept() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		$sUserId=getRequestStr('idUser',null,'post');
		/**
		 * Если пользователь не авторизирован, возвращаем ошибку
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('need_authorization'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * При попытке добавить в друзья себя, возвращаем ошибку
		 */
		if ($this->oUserCurrent->getId()==$sUserId) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_add_self'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Если пользователь не найден, возвращаем ошибку
		 */
		if( !$oUser=$this->User_GetUserById($sUserId) ) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_not_found'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserProfile=$oUser;
		/**
		 * Получаем статус дружбы между пользователями
		 */
		$oFriend=$this->User_GetFriend($oUser->getId(),$this->oUserCurrent->getId());
		/**
		 * При попытке потдвердить ранее отклоненную заявку,
		 * проверяем, чтобы изменяющий был принимающей стороной
		 */
		if($oFriend
			&& ($oFriend->getStatusFrom()==ModuleUser::USER_FRIEND_OFFER||$oFriend->getStatusFrom()==ModuleUser::USER_FRIEND_ACCEPT)
			&& ($oFriend->getStatusTo()==ModuleUser::USER_FRIEND_REJECT||$oFriend->getStatusTo()==ModuleUser::USER_FRIEND_NULL)
			&& $oFriend->getUserTo()==$this->oUserCurrent->getId()) {
			/**
			 * Меняем статус с отвергнутое, на акцептованное
			 */
			$oFriend->setStatusByUserId(ModuleUser::USER_FRIEND_ACCEPT,$this->oUserCurrent->getId());
			if($this->User_UpdateFriend($oFriend)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('user_friend_add_ok'),$this->Lang_Get('attention'));
				$this->NoticeFriendOffer($oUser,'accept');
				/**
				 * Добавляем событие в ленту
				 */
				$this->Stream_write($oFriend->getUserFrom(), 'add_friend', $oFriend->getUserTo());
				$this->Stream_write($oFriend->getUserTo(), 'add_friend', $oFriend->getUserFrom());
				/**
				 * Добавляем пользователей к друг другу в ленту активности
				 */
				$this->Stream_subscribeUser($oFriend->getUserFrom(), $oFriend->getUserTo());
				$this->Stream_subscribeUser($oFriend->getUserTo(), $oFriend->getUserFrom());

				$oViewerLocal=$this->GetViewerLocal();
				$oViewerLocal->Assign('oUserFriend',$oFriend);
				$this->Viewer_AssignAjax('sToggleText',$oViewerLocal->Fetch("actions/ActionProfile/friend_item.tpl"));

			} else {
				return $this->EventErrorDebug();
			}
			return;
		}

		return $this->EventErrorDebug();
	}
	/**
	 * Отправляет пользователю Talk уведомление о принятии или отклонении его заявки
	 *
	 * @param ModuleUser_EntityUser $oUser
	 * @param string $sAction
	 */
	protected function NoticeFriendOffer($oUser,$sAction) {
		/**
		 * Проверяем допустимость действия
		 */
		if(!in_array($sAction,array('accept','reject'))) {
			return false;
		}
		/**
		 * Проверяем настройки (нужно ли отправлять уведомление)
		 */
		if(!Config::Get("module.user.friend_notice.{$sAction}")) {
			return false;
		}

		$sTitle=$this->Lang_Get("user_friend_{$sAction}_notice_title");
		$sText=$this->Lang_Get(
			"user_friend_{$sAction}_notice_text",
			array(
				'login'=>$this->oUserCurrent->getLogin(),
			)
		);
		$oTalk=$this->Talk_SendTalk($sTitle,$sText,$this->oUserCurrent,array($oUser),false,false);
		$this->Talk_DeleteTalkUserByArray($oTalk->getId(),$this->oUserCurrent->getId());
	}
	/**
	 * Обработка Ajax добавления в друзья
	 */
	public function EventAjaxFriendAdd() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		$sUserId=getRequestStr('idUser');
		$sUserText=getRequestStr('userText','');
		/**
		 * Если пользователь не авторизирован, возвращаем ошибку
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('need_authorization'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * При попытке добавить в друзья себя, возвращаем ошибку
		 */
		if ($this->oUserCurrent->getId()==$sUserId) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_add_self'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Если пользователь не найден, возвращаем ошибку
		 */
		if( !$oUser=$this->User_GetUserById($sUserId) ) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_not_found'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserProfile=$oUser;
		/**
		 * Получаем статус дружбы между пользователями
		 */
		$oFriend=$this->User_GetFriend($oUser->getId(),$this->oUserCurrent->getId());
		/**
		 * Если связи ранее не было в базе данных, добавляем новую
		 */
		if( !$oFriend ) {
			$this->SubmitAddFriend($oUser,$sUserText,$oFriend);
			return;
		}
		/**
		 * Если статус связи соответствует статусам отправленной и акцептованной заявки,
		 * то предупреждаем что этот пользователь уже является нашим другом
		 */
		if($oFriend->getFriendStatus()==ModuleUser::USER_FRIEND_OFFER + ModuleUser::USER_FRIEND_ACCEPT) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_already_exist'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Если пользователь ранее отклонил нашу заявку,
		 * возвращаем сообщение об ошибке
		 */
		if($oFriend->getUserFrom()==$this->oUserCurrent->getId()
			&& $oFriend->getStatusTo()==ModuleUser::USER_FRIEND_REJECT ) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_offer_reject'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Если дружба была удалена, то проверяем кто ее удалил
		 * и разрешаем восстановить только удалившему
		 */
		if($oFriend->getFriendStatus()>ModuleUser::USER_FRIEND_DELETE
			&& $oFriend->getFriendStatus()<ModuleUser::USER_FRIEND_REJECT) {
			/**
			 * Определяем статус связи текущего пользователя
			 */
			$iStatusCurrent	= $oFriend->getStatusByUserId($this->oUserCurrent->getId());

			if($iStatusCurrent==ModuleUser::USER_FRIEND_DELETE) {
				/**
				 * Меняем статус с удаленного, на акцептованное
				 */
				$oFriend->setStatusByUserId(ModuleUser::USER_FRIEND_ACCEPT,$this->oUserCurrent->getId());
				if($this->User_UpdateFriend($oFriend)) {
					/**
					 * Добавляем событие в ленту
					 */
					$this->Stream_write($oFriend->getUserFrom(), 'add_friend', $oFriend->getUserTo());
					$this->Stream_write($oFriend->getUserTo(), 'add_friend', $oFriend->getUserFrom());
					$this->Message_AddNoticeSingle($this->Lang_Get('user_friend_add_ok'),$this->Lang_Get('attention'));

					$oViewerLocal=$this->GetViewerLocal();
					$oViewerLocal->Assign('oUserFriend',$oFriend);
					$this->Viewer_AssignAjax('sToggleText',$oViewerLocal->Fetch("actions/ActionProfile/friend_item.tpl"));

				} else {
					return $this->EventErrorDebug();
				}
				return;
			} else {
				$this->Message_AddErrorSingle(
					$this->Lang_Get('user_friend_add_deleted'),
					$this->Lang_Get('error')
				);
				return;
			}
		}
	}
	/**
	 * Функция создает локальный объект вьювера для рендеринга html-объектов в ajax запросах
	 *
	 * @return ModuleViewer
	 */
	protected function GetViewerLocal() {
		/**
		 * Получаем HTML код inject-объекта
		 */
		$oViewerLocal=$this->Viewer_GetLocalViewer();
		$oViewerLocal->Assign('oUserCurrent',$this->oUserCurrent);
		$oViewerLocal->Assign('oUserProfile',$this->oUserProfile);

		$oViewerLocal->Assign('USER_FRIEND_NULL',ModuleUser::USER_FRIEND_NULL);
		$oViewerLocal->Assign('USER_FRIEND_OFFER',ModuleUser::USER_FRIEND_OFFER);
		$oViewerLocal->Assign('USER_FRIEND_ACCEPT',ModuleUser::USER_FRIEND_ACCEPT);
		$oViewerLocal->Assign('USER_FRIEND_REJECT',ModuleUser::USER_FRIEND_REJECT);
		$oViewerLocal->Assign('USER_FRIEND_DELETE',ModuleUser::USER_FRIEND_DELETE);

		return $oViewerLocal;
	}
	/**
	 * Обработка добавления в друзья
	 *
	 * @param $oUser
	 * @param $sUserText
	 * @param null $oFriend
	 * @return bool
	 */
	protected function SubmitAddFriend($oUser,$sUserText,$oFriend=null) {
		/**
		 * Ограничения на добавления в друзья, т.к. приглашение отправляется в личку, то и ограничиваем по ней
		 */
		if (!$this->ACL_CanSendTalkTime($this->oUserCurrent)) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_friend_add_time_limit'),$this->Lang_Get('error'));
			return false;
		}
		/**
		 * Обрабатываем текст заявки
		 */
		$sUserText=$this->Text_Parser($sUserText);
		/**
		 * Создаем связь с другом
		 */
		$oFriendNew=Engine::GetEntity('User_Friend');
		$oFriendNew->setUserTo($oUser->getId());
		$oFriendNew->setUserFrom($this->oUserCurrent->getId());
		// Добавляем заявку в друзья
		$oFriendNew->setStatusFrom(ModuleUser::USER_FRIEND_OFFER);
		$oFriendNew->setStatusTo(ModuleUser::USER_FRIEND_NULL);

		$bStateError=($oFriend)
			? !$this->User_UpdateFriend($oFriendNew)
			: !$this->User_AddFriend($oFriendNew);

		if ( !$bStateError ) {
			$this->Message_AddNoticeSingle($this->Lang_Get('user_friend_offer_send'),$this->Lang_Get('attention'));

			$sTitle=$this->Lang_Get(
				'user_friend_offer_title',
				array(
					'login'=>$this->oUserCurrent->getLogin(),
					'friend'=>$oUser->getLogin()
				)
			);

			require_once Config::Get('path.framework.libs_vendor.server').'/XXTEA/encrypt.php';
			$sCode=$this->oUserCurrent->getId().'_'.$oUser->getId();
			$sCode=rawurlencode(base64_encode(xxtea_encrypt($sCode, Config::Get('module.talk.encrypt'))));

			$aPath=array(
				'accept'=>Router::GetPath('profile').'friendoffer/accept/?code='.$sCode,
				'reject'=>Router::GetPath('profile').'friendoffer/reject/?code='.$sCode
			);

			$sText=$this->Lang_Get(
				'user_friend_offer_text',
				array(
					'login'=>$this->oUserCurrent->getLogin(),
					'accept_path'=>$aPath['accept'],
					'reject_path'=>$aPath['reject'],
					'user_text'=>$sUserText
				)
			);
			$oTalk=$this->Talk_SendTalk($sTitle,$sText,$this->oUserCurrent,array($oUser),false,false);
			/**
			 * Отправляем пользователю заявку
			 */
			$this->Notify_SendUserFriendNew(
				$oUser,$this->oUserCurrent,$sUserText,
				Router::GetPath('talk').'read/'.$oTalk->getId().'/'
			);
			/**
			 * Удаляем отправляющего юзера из переписки
			 */
			$this->Talk_DeleteTalkUserByArray($oTalk->getId(),$this->oUserCurrent->getId());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
		}

		$oViewerLocal=$this->GetViewerLocal();
		$oViewerLocal->Assign('oUserFriend',$oFriendNew);
		$this->Viewer_AssignAjax('sToggleText',$oViewerLocal->Fetch("actions/ActionProfile/friend_item.tpl"));
	}
	/**
	 * Удаление пользователя из друзей
	 */
	public function EventAjaxFriendDelete() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		$sUserId=getRequestStr('idUser',null,'post');
		/**
		 * Если пользователь не авторизирован, возвращаем ошибку
		 */
		if (!$this->User_IsAuthorization()) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('need_authorization'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserCurrent=$this->User_GetUserCurrent();
		/**
		 * При попытке добавить в друзья себя, возвращаем ошибку
		 */
		if ($this->oUserCurrent->getId()==$sUserId) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_add_self'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Если пользователь не найден, возвращаем ошибку
		 */
		if( !$oUser=$this->User_GetUserById($sUserId) ) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_del_no'),
				$this->Lang_Get('error')
			);
			return;
		}
		$this->oUserProfile=$oUser;
		/**
		 * Получаем статус дружбы между пользователями.
		 * Если статус не определен, или отличается от принятой заявки,
		 * возвращаем ошибку
		 */
		$oFriend=$this->User_GetFriend($oUser->getId(),$this->oUserCurrent->getId());
		$aAllowedFriendStatus = array(ModuleUser::USER_FRIEND_ACCEPT+ModuleUser::USER_FRIEND_OFFER,ModuleUser::USER_FRIEND_ACCEPT+ModuleUser::USER_FRIEND_ACCEPT);
		if(!$oFriend || !in_array($oFriend->getFriendStatus(),$aAllowedFriendStatus)) {
			$this->Message_AddErrorSingle(
				$this->Lang_Get('user_friend_del_no'),
				$this->Lang_Get('error')
			);
			return;
		}
		/**
		 * Удаляем из друзей
		 */
		if( $this->User_DeleteFriend($oFriend) ) {
			$this->Message_AddNoticeSingle($this->Lang_Get('user_friend_del_ok'),$this->Lang_Get('attention'));

			$oViewerLocal=$this->GetViewerLocal();
			$oViewerLocal->Assign('oUserFriend',$oFriend);
			$this->Viewer_AssignAjax('sToggleText',$oViewerLocal->Fetch("actions/ActionProfile/friend_item.tpl"));

			/**
			 * Отправляем пользователю сообщение об удалении дружеской связи
			 */
			if(Config::Get('module.user.friend_notice.delete')) {
				$sText=$this->Lang_Get(
					'user_friend_del_notice_text',
					array(
						'login'=>$this->oUserCurrent->getLogin(),
					)
				);
				$oTalk=$this->Talk_SendTalk(
					$this->Lang_Get('user_friend_del_notice_title'),
					$sText,$this->oUserCurrent,
					array($oUser),false,false
				);
				$this->Talk_DeleteTalkUserByArray($oTalk->getId(),$this->oUserCurrent->getId());
			}
			return;
		} else {
			return $this->EventErrorDebug();
		}
	}
	/**
	 * Обработка подтверждения старого емайла при его смене
	 * TODO: Перенести в экшн Settings
	 */
	public function EventChangemailConfirmFrom() {
		if (!($oChangemail=$this->User_GetUserChangemailByCodeFrom($this->GetParamEventMatch(1,0)))) {
			return parent::EventNotFound();
		}

		if ($oChangemail->getConfirmFrom() or strtotime($oChangemail->getDateExpired())<time()) {
			return parent::EventNotFound();
		}

		$oChangemail->setConfirmFrom(1);
		$this->User_UpdateUserChangemail($oChangemail);

		/**
		 * Отправляем уведомление
		 */
		$oUser=$this->User_GetUserById($oChangemail->getUserId());
		$this->Notify_Send($oChangemail->getMailTo(),
						   Config::Get('module.notify.prefix').'.user_changemail_to.tpl',
						   $this->Lang_Get('notify_subject_user_changemail'),
						   array(
							   'oUser' => $oUser,
							   'oChangemail' => $oChangemail,
						   ));

		$this->Viewer_Assign('sText',$this->Lang_Get('settings_profile_mail_change_to_notice'));
		$this->SetTemplate('actions/ActionSettings/account.change_email_confirm.tpl');
	}
	/**
	 * Обработка подтверждения нового емайла при смене старого
	 */
	public function EventChangemailConfirmTo() {
		if (!($oChangemail=$this->User_GetUserChangemailByCodeTo($this->GetParamEventMatch(1,0)))) {
			return parent::EventNotFound();
		}

		if (!$oChangemail->getConfirmFrom() or $oChangemail->getConfirmTo() or strtotime($oChangemail->getDateExpired())<time()) {
			return parent::EventNotFound();
		}

		$oChangemail->setConfirmTo(1);
		$oChangemail->setDateUsed(date("Y-m-d H:i:s"));
		$this->User_UpdateUserChangemail($oChangemail);

		$oUser=$this->User_GetUserById($oChangemail->getUserId());
		$oUser->setMail($oChangemail->getMailTo());
		$this->User_Update($oUser);

		/**
		 * Меняем емайл в подписках
		 */
		if ($oChangemail->getMailFrom()) {
			$this->Subscribe_ChangeSubscribeMail($oChangemail->getMailFrom(),$oChangemail->getMailTo(),$oUser->getId());
		}

		$this->Viewer_Assign('sText',$this->Lang_Get('settings_profile_mail_change_ok',array('mail'=>htmlspecialchars($oChangemail->getMailTo()))));
		$this->SetTemplate('actions/ActionSettings/account.change_email_confirm.tpl');
	}
	/**
	 * Выполняется при завершении работы экшена
	 */
	public function EventShutdown() {
		if (!$this->oUserProfile)	 {
			return ;
		}
		/**
		 * Загружаем в шаблон необходимые переменные
		 */
		$iCountTopicFavourite=$this->Topic_GetCountTopicsFavouriteByUserId($this->oUserProfile->getId());
		$iCountTopicUser=$this->Topic_GetCountTopicsPersonalByUser($this->oUserProfile->getId(),1);
		$iCountCommentUser=$this->Comment_GetCountCommentsByUserId($this->oUserProfile->getId(),'topic');
		$iCountCommentFavourite=$this->Comment_GetCountCommentsFavouriteByUserId($this->oUserProfile->getId());
		$iCountNoteUser=$this->User_GetCountUserNotesByUserId($this->oUserProfile->getId());

		$this->Viewer_Assign('oUserProfile',$this->oUserProfile);
		$this->Viewer_Assign('iCountTopicUser',$iCountTopicUser);
		$this->Viewer_Assign('iCountCommentUser',$iCountCommentUser);
		$this->Viewer_Assign('iCountTopicFavourite',$iCountTopicFavourite);
		$this->Viewer_Assign('iCountCommentFavourite',$iCountCommentFavourite);
		$this->Viewer_Assign('iCountNoteUser',$iCountNoteUser);
		$this->Viewer_Assign('iCountWallUser',$this->Wall_GetCountWall(array('wall_user_id'=>$this->oUserProfile->getId(),'pid'=>null)));
		/**
		 * Общее число публикация и избранного
		 */
		$this->Viewer_Assign('iCountCreated',(($this->oUserCurrent and $this->oUserCurrent->getId()==$this->oUserProfile->getId()) ? $iCountNoteUser : 0) +$iCountTopicUser+$iCountCommentUser);
		$this->Viewer_Assign('iCountFavourite',$iCountCommentFavourite+$iCountTopicFavourite);
		/**
		 * Заметка текущего пользователя о юзере
		 */
		if ($this->oUserCurrent) {
			$this->Viewer_Assign('oUserNote',$this->oUserProfile->getUserNote());
		}
		$this->Viewer_Assign('iCountFriendsUser',$this->User_GetCountUsersFriend($this->oUserProfile->getId()));

		$this->Viewer_Assign('sMenuSubItemSelect',$this->sMenuSubItemSelect);
		$this->Viewer_Assign('sMenuHeadItemSelect',$this->sMenuHeadItemSelect);
		$this->Viewer_Assign('USER_FRIEND_NULL',ModuleUser::USER_FRIEND_NULL);
		$this->Viewer_Assign('USER_FRIEND_OFFER',ModuleUser::USER_FRIEND_OFFER);
		$this->Viewer_Assign('USER_FRIEND_ACCEPT',ModuleUser::USER_FRIEND_ACCEPT);
		$this->Viewer_Assign('USER_FRIEND_REJECT',ModuleUser::USER_FRIEND_REJECT);
		$this->Viewer_Assign('USER_FRIEND_DELETE',ModuleUser::USER_FRIEND_DELETE);
	}
}