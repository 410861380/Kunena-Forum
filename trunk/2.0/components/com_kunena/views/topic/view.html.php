<?php
/**
 * @version		$Id$
 * Kunena Component
 * @package Kunena
 *
 * @Copyright (C) 2008 - 2010 Kunena Team All rights reserved
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 */
defined ( '_JEXEC' ) or die ();

kimport ( 'kunena.view' );

/**
 * Topic View
 */
class KunenaViewTopic extends KunenaView {
	var $topic_subscribe = null;
	var $topic_favorite = null;
	var $topic_reply = null;
	var $topic_new = null;
	var $topic_sticky = null;
	var $topic_lock = null;
	var $topic_delete = null;
	var $topic_moderate = null;

	function displayDefault($tpl = null) {
		$this->assignRef ( 'category', $this->get ( 'Category' ) );
		$this->assignRef ( 'topic', $this->get ( 'Topic' ) );
		$channels = $this->category->getChannels();
		if (! $this->category->authorise('read')) {
			// User is not allowed to see the category
			$this->setError($this->category->getError());
		} elseif (! $this->topic) {
			// Moved topic loop detected (1 -> 2 -> 3 -> 2)
			// FIXME: translate
			$this->setError(JText::_('COM_KUNENA_VIEW_TOPIC_ERROR_LOOP'));
		} elseif (! $this->topic->authorise('read')) {
			// User is not allowed to see the topic
			$this->setError($this->topic->getError());
		} elseif ($this->state->get('item.id') != $this->topic->id || ($this->category->id != $this->topic->category_id && !isset($channels[$this->topic->category_id]))) {
			// Topic has been moved or it doesn't belong to the current category
			$db = JFactory::getDBO();
			$query = "SELECT COUNT(*) FROM #__kunena_messages WHERE thread={$db->Quote($this->topic->id)} AND hold IN ({$this->state->get('hold')}) AND id<={$db->Quote($this->state->get('item.id'))}";
			$db->setQuery ( $query );
			$replyCount = $db->loadResult ();
			if (KunenaError::checkDatabaseError()) return;
			$app = JFactory::getApplication();
			$app->redirect(CKunenaLink::GetThreadPageURL ( 'view', $this->topic->category_id, $this->topic->id, $replyCount, $this->state->get('list.limit'), $this->state->get('item.id'), false ));
		}

		$errors = $this->getErrors();
		if ($errors) {
			return $this->displayNoAccess($errors);
		}

		$this->assignRef ( 'messages', $this->get ( 'Messages' ) );
		$this->assignRef ( 'total', $this->get ( 'Total' ) );

		// If page does not exist, redirect to the last page
		if ($this->total <= $this->state->get('list.start')) {
			$app = JFactory::getApplication();
			$app->redirect(CKunenaLink::GetThreadPageURL('view', $this->topic->category_id, $this->topic->id, $this->total, $this->state->get('list.start'), '', false));
		}

		$this->assignRef ( 'moderators', $this->get ( 'Moderators' ) );
		$this->assignRef ( 'usertopic',$this->topic->getUserTopic());
		$this->headerText =  JText::_('COM_KUNENA_MENU_LATEST_DESC');
		$this->title = JText::_('COM_KUNENA_ALL_DISCUSSIONS');
		// FIXME:
		$this->pagination = $this->getPagination ( 7 );
		$this->me = KunenaFactory::getUser();
		$this->config = KunenaFactory::getConfig();

		// Mark topic read
		$this->topic->markRead ();
		$this->topic->hits++;
		$this->topic->save();

		$this->keywords = $this->topic->getKeywords(false, ', ');

		$this->buttons();

		//meta description and keywords
		// TODO: use real keywords, too
		$metaKeys = $this->escape ( "{$this->topic->subject}, {$this->category->getParent()->name}, {$this->config->board_title}, " . JText::_('COM_KUNENA_GEN_FORUM') . ', ' . JFactory::getapplication()->getCfg ( 'sitename' ) );

		// Create Meta Description form the content of the first message
		// better for search results display but NOT for search ranking!
		$metaDesc = KunenaHtmlParser::stripBBCode($this->topic->first_post_message);
		$metaDesc = preg_replace('/\s+/', ' ', $metaDesc); // remove newlines
		$metaDesc = preg_replace('/^[^\w0-9]+/', '', $metaDesc); // remove characters at the beginning that are not letters or numbers
		$metaDesc = trim($metaDesc); // Remove trailing spaces and beginning

		// remove multiple spaces
		while (strpos($metaDesc, '  ') !== false){
			$metaDesc = str_replace('  ', ' ', $metaDesc);
		}

		// limit to 185 characters - google will cut off at ~150
		if (strlen($metaDesc) > 185){
			$metaDesc = rtrim(JString::substr($metaDesc, 0, 182)).'...';
		}

		$this->document->setMetadata ( 'keywords', $metaKeys );
		$this->document->setDescription ( $this->escape($metaDesc) );

		$this->display($tpl);
	}

	function buttons() {
		$catid = $this->state->get('item.catid');
		$id = $this->state->get('item.id');

		// Subscribe topic
		if ($this->usertopic->subscribed) {
			// this user is allowed to unsubscribe
			$this->topic_subscribe = CKunenaLink::GetTopicPostLink ( 'unsubscribe', $catid, $id, CKunenaTools::showButton ( 'subscribe', JText::_('COM_KUNENA_BUTTON_UNSUBSCRIBE_TOPIC') ), 'nofollow', 'kicon-button kbuttonuser btn-left', JText::_('COM_KUNENA_BUTTON_UNSUBSCRIBE_TOPIC_LONG') );
		} elseif ($this->topic->authorise('subscribe')) {
			// this user is allowed to subscribe
			$this->topic_subscribe = CKunenaLink::GetTopicPostLink ( 'subscribe', $catid, $id, CKunenaTools::showButton ( 'subscribe', JText::_('COM_KUNENA_BUTTON_SUBSCRIBE_TOPIC') ), 'nofollow', 'kicon-button kbuttonuser btn-left', JText::_('COM_KUNENA_BUTTON_SUBSCRIBE_TOPIC_LONG') );
		}

		// Favorite topic
		if ($this->usertopic->favorite) {
			// this user is allowed to unfavorite
			$this->topic_favorite = CKunenaLink::GetTopicPostLink ( 'unfavorite', $catid, $id, CKunenaTools::showButton ( 'favorite', JText::_('COM_KUNENA_BUTTON_UNFAVORITE_TOPIC') ), 'nofollow', 'kicon-button kbuttonuser btn-left', JText::_('COM_KUNENA_BUTTON_UNFAVORITE_TOPIC_LONG') );
		} elseif ($this->topic->authorise('favorite')) {
			// this user is allowed to add a favorite
			$this->topic_favorite = CKunenaLink::GetTopicPostLink ( 'favorite', $catid, $id, CKunenaTools::showButton ( 'favorite', JText::_('COM_KUNENA_BUTTON_FAVORITE_TOPIC') ), 'nofollow', 'kicon-button kbuttonuser btn-left', JText::_('COM_KUNENA_BUTTON_FAVORITE_TOPIC_LONG') );
		}

		// Reply topic
		if ($this->topic->authorise('reply')) {
			// this user is allowed to reply to this topic
			$this->topic_reply = CKunenaLink::GetTopicPostReplyLink ( 'reply', $catid, $this->topic->id, CKunenaTools::showButton ( 'reply', JText::_('COM_KUNENA_BUTTON_REPLY_TOPIC') ), 'nofollow', 'kicon-button kbuttoncomm btn-left', JText::_('COM_KUNENA_BUTTON_REPLY_TOPIC_LONG') );
		}

		// New topic
		if ($this->category->authorise('topic.create')) {
			//this user is allowed to post a new topic
			$this->topic_new = CKunenaLink::GetPostNewTopicLink ( $catid, CKunenaTools::showButton ( 'newtopic', JText::_('COM_KUNENA_BUTTON_NEW_TOPIC') ), 'nofollow', 'kicon-button kbuttoncomm btn-left', JText::_('COM_KUNENA_BUTTON_NEW_TOPIC_LONG') );
		}

		// Moderator specific stuff
		if ($this->category->authorise('moderate')) {
			if (!$this->topic->ordering) {
				$this->topic_sticky = CKunenaLink::GetTopicPostLink ( 'sticky', $catid, $id, CKunenaTools::showButton ( 'sticky', JText::_('COM_KUNENA_BUTTON_STICKY_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_STICKY_TOPIC_LONG') );
			} else {
				$this->topic_sticky = CKunenaLink::GetTopicPostLink ( 'unsticky', $catid, $id, CKunenaTools::showButton ( 'sticky', JText::_('COM_KUNENA_BUTTON_UNSTICKY_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_UNSTICKY_TOPIC_LONG') );
			}

			if (!$this->topic->locked) {
				$this->topic_lock = CKunenaLink::GetTopicPostLink ( 'lock', $catid, $id, CKunenaTools::showButton ( 'lock', JText::_('COM_KUNENA_BUTTON_LOCK_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_LOCK_TOPIC_LONG') );
			} else {
				$this->topic_lock = CKunenaLink::GetTopicPostLink ( 'unlock', $catid, $id, CKunenaTools::showButton ( 'lock', JText::_('COM_KUNENA_BUTTON_UNLOCK_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_UNLOCK_TOPIC_LONG') );
			}
			$this->topic_delete = CKunenaLink::GetTopicPostLink ( 'deletethread', $catid, $id, CKunenaTools::showButton ( 'delete', JText::_('COM_KUNENA_BUTTON_DELETE_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_DELETE_TOPIC_LONG') );
			$this->topic_moderate = CKunenaLink::GetTopicPostReplyLink ( 'moderatethread', $catid, $id, CKunenaTools::showButton ( 'moderate', JText::_('COM_KUNENA_BUTTON_MODERATE_TOPIC') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_MODERATE') );
		}
	}

	function displayPathway() {
		CKunenaTools::loadTemplate('/pathway.php');
	}

	function displayPoll() {
		if ($this->config->pollenabled == "1" && $this->topic->poll_id) {
			if ( $this->category->allow_polls ) {
				require_once (KPATH_SITE . DS . 'lib' .DS. 'kunena.poll.class.php');
				$kunena_polls = CKunenaPolls::getInstance();
				$kunena_polls->showPollbox();
			}
		}
	}

	function displayTopicActions($location=0) {
		static $locations = array('top', 'bottom');
		$location ^= 1;
		$this->goto = '<a name="forum'.$locations[$location].'"></a>';
		$this->goto .= CKunenaLink::GetSamePageAnkerLink ( 'forum'.$locations[$location], CKunenaTools::showIcon ( 'kforum'.$locations[$location], JText::_('COM_KUNENA_GEN_GOTO'.$locations[$location] ) ), 'nofollow', 'kbuttongoto');
		echo $this->loadTemplate('actions');
	}

	function displayForumJump() {
		if ($this->config->enableforumjump) {
			CKunenaTools::loadTemplate('/forumjump.php');
		}
	}

	function displayMessageProfile($layout = '') {
		static $profiles = array ();

		if (! isset ( $profiles [$this->profile->userid] )) {
			// Modify profile values by integration
			$triggerParams = array ('userid' => $this->profile->userid, 'userinfo' => &$this->profile );
			$integration = KunenaFactory::getProfile();
			$integration->trigger ( 'profileIntegration', $triggerParams );

			if ($this->config->showuserstats) {
				if ($this->config->userlist_usertype)
					$this->usertype = $this->profile->getType ( $this->topic->category_id );
				$this->userrankimage = $this->profile->getRank ( $this->topic->category_id, 'image' );
				$this->userranktitle = $this->profile->getRank ( $this->topic->category_id, 'title' );
				$this->userposts = $this->profile->posts;
				$activityIntegration = KunenaFactory::getActivityIntegration ();
				$this->userpoints = $activityIntegration->getUserPoints ( $this->profile->userid );
				$this->usermedals = $activityIntegration->getUserMedals ( $this->profile->userid );
			}

			//karma points and buttons
			$me = KunenaFactory::getUser ();
			if ($this->config->showkarma && $this->profile->userid) {
				$this->userkarma = JText::_ ( 'COM_KUNENA_KARMA' ) . ": " . $this->profile->karma;

				if ($me->userid && $me->userid != $this->profile->userid) {
					$this->userkarma .= ' ' . CKunenaLink::GetKarmaLink ( 'decrease', $this->topic->category_id, $this->message->id, $this->profile->userid, '<span class="kkarma-minus" alt="Karma-" border="0" title="' . JText::_ ( 'COM_KUNENA_KARMA_SMITE' ) . '"> </span>' );
					$this->userkarma .= ' ' . CKunenaLink::GetKarmaLink ( 'increase', $this->topic->category_id, $this->message->id, $this->profile->userid, '<span class="kkarma-plus" alt="Karma+" border="0" title="' . JText::_ ( 'COM_KUNENA_KARMA_APPLAUD' ) . '"> </span>' );
				}
			}

			$this->personalText = KunenaHtmlParser::parseText ( $this->profile->personalText );

			$profiles [$this->profile->userid] = $this->loadTemplate ( "profile_{$layout}" );
		}
		echo $profiles [$this->profile->userid];
	}

	function displayMessageContents() {
		//Show admins the IP address of the user:
		if ($this->message->ip && ($this->category->authorise('admin') || ($this->category->authorise('moderate') && !$this->config->hide_ip))) {
			$this->ipLink = CKunenaLink::GetMessageIPLink ( $this->message->ip );
		}
		$this->signatureHtml = KunenaHtmlParser::parseBBCode ( $this->profile->signature );
		echo $this->loadTemplate("message");
	}

	function displayMessageActions() {
		$me = KunenaFactory::getUser();

		//Thankyou info and buttons
		if ($this->config->showthankyou && $this->profile->userid) {
			require_once(KPATH_SITE .DS. 'lib'.DS.'kunena.thankyou.php');
			$thankyou = new CKunenaThankyou();
			$this->thankyou = $thankyou->getThankYouUser($this->message->id);

			if($me->userid && $me->userid != $this->profile->userid) {
				$this->message_thankyou = CKunenaLink::GetThankYouLink ( $this->topic->category_id, $this->message->id, $this->profile->userid , CKunenaTools::showButton ( 'thankyou', JText::_('COM_KUNENA_BUTTON_THANKYOU') ), JText::_('COM_KUNENA_BUTTON_THANKYOU_LONG'), 'kicon-button kbuttonuser btn-left');
			}
		}

		if ($this->topic->authorise('reply')) {
			//user is allowed to reply/quote
			if ($me->userid) {
				$this->message_quickreply = CKunenaLink::GetTopicPostReplyLink ( 'reply', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'reply', JText::_('COM_KUNENA_BUTTON_QUICKREPLY') ), 'nofollow', 'kicon-button kbuttoncomm btn-left kqreply', JText::_('COM_KUNENA_BUTTON_QUICKREPLY_LONG'), ' id="kreply'.$this->message->id.'"' );
			}
			$this->message_reply = CKunenaLink::GetTopicPostReplyLink ( 'reply', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'reply', JText::_('COM_KUNENA_BUTTON_REPLY') ), 'nofollow', 'kicon-button kbuttoncomm btn-left', JText::_('COM_KUNENA_BUTTON_REPLY_LONG') );
			$this->message_quote = CKunenaLink::GetTopicPostReplyLink ( 'quote', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'quote', JText::_('COM_KUNENA_BUTTON_QUOTE') ), 'nofollow', 'kicon-button kbuttoncomm btn-left', JText::_('COM_KUNENA_BUTTON_QUOTE_LONG') );
		} else {
			//user is not allowed to write a post
			if ($this->topic->locked) {
				$this->message_closed = JText::_('COM_KUNENA_POST_LOCK_SET');
			} else {
				$this->message_closed = JText::_('COM_KUNENA_VIEW_DISABLED');
			}
		}

		$this->class = 'class="kmsg"';

		//Offer an moderator a few tools
		$this->message_delete = $this->message_undelete = $this->message_permdelete = $this->message_publish = '';
		if (CKunenaTools::isModerator ( $me->userid, $this->topic->category_id )) {
			unset($this->message_closed);
			$this->message_edit = CKunenaLink::GetTopicPostReplyLink ( 'edit', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'edit', JText::_('COM_KUNENA_BUTTON_EDIT') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_EDIT_LONG') );
			$this->message_moderate = CKunenaLink::GetTopicPostReplyLink ( 'moderate', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'moderate', JText::_('COM_KUNENA_BUTTON_MODERATE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_MODERATE_LONG') );
			if ($this->message->hold == 1) {
				$this->message_publish = CKunenaLink::GetTopicPostLink ( 'approve', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'approve', JText::_('COM_KUNENA_BUTTON_APPROVE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_APPROVE_LONG') );
				$this->class = 'class="kmsg kunapproved"';
			}
			if ($this->message->hold == 2 || $this->message->hold == 3) {
				$this->class = 'class="kmsg kunapproved"';
				$this->message_undelete = CKunenaLink::GetTopicPostLink ( 'undelete', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'undelete', JText::_('COM_KUNENA_BUTTON_UNDELETE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_UNDELETE_LONG') );
				$this->message_permdelete = CKunenaLink::GetTopicPostLink ( 'permdelete', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'permdelete', JText::_('COM_KUNENA_BUTTON_PERMDELETE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_PERMDELETE_LONG') );
			} else {
				$this->message_delete = CKunenaLink::GetTopicPostLink ( 'delete', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'delete', JText::_('COM_KUNENA_BUTTON_DELETE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_DELETE_LONG') );
			}
		}
		else if ($this->config->useredit && $me->userid && $me->userid == $this->profile->userid) {
			if ($this->message->authorise('edit')) {
				$this->message_edit = CKunenaLink::GetTopicPostReplyLink ( 'edit', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'edit', JText::_('COM_KUNENA_BUTTON_EDIT') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_EDIT_LONG') );
				if ( $this->config->userdeletetmessage == '1' ) {
					if ($this->replynum == $this->replycnt) $this->message_delete = CKunenaLink::GetTopicPostLink ( 'delete', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'delete', JText::_('COM_KUNENA_BUTTON_DELETE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_DELETE_LONG') );
				} else if ( $this->config->userdeletetmessage == '2' ) {
					$this->message_delete = CKunenaLink::GetTopicPostLink ( 'delete', $this->topic->category_id, $this->message->id, CKunenaTools::showButton ( 'delete', JText::_('COM_KUNENA_BUTTON_DELETE') ), 'nofollow', 'kicon-button kbuttonmod btn-left', JText::_('COM_KUNENA_BUTTON_DELETE_LONG') );
				}
			}
		}
		echo $this->loadTemplate("message_actions");
	}

	function displayMessages() {
		$this->mmm = 0;
		$replydir = $this->state->get('list.direction') == 'asc' ? 1 : -1;
		if ($replydir < 0) $this->replynum = $this->total - $this->state->get('list.start') + 1;
		else $this->replynum = $this->state->get('list.start');

		foreach ( $this->messages as $this->message ) {
			$this->profile = KunenaFactory::getUser($this->message->userid);

			$this->mmm ++;
			$this->replynum += $replydir;

			$this->class = 'class="kmsg"';
			// Link to individual message
			if ($this->config->ordering_system == 'replyid') {
				$this->numLink = CKunenaLink::GetSamePageAnkerLink( $this->message->id, '#' . $this->replynum );
			} else {
				$this->numLink = CKunenaLink::GetSamePageAnkerLink ( $this->message->id, '#' . $this->message->id );
			}
			// New post suffix for class
			$this->msgsuffix = '';
			if ($this->message->isNew()) {
				$this->msgsuffix = '-new';
			}

			echo $this->loadtemplate('left');
		}
	}

	function getPagination($maxpages) {
		$catid = $this->state->get('item.catid');
		$threadid = $this->state->get('item.id');
		$start = $this->state->get('list.start');
		$limit = $this->state->get('list.limit');
		$page = intval($start/$limit)+1;
		$totalpages = intval(($this->total-1)/$limit)+1;

		$startpage = ($page - floor ( $maxpages / 2 ) < 1) ? 1 : $page - floor ( $maxpages / 2 );
		$endpage = $startpage + $maxpages;
		if ($endpage > $totalpages) {
			$startpage = ($totalpages - $maxpages) < 1 ? 1 : $totalpages - $maxpages;
			$endpage = $totalpages;
		}

		$output = '<ul class="kpagination">';
		$output .= '<li class="page">' . JText::_('COM_KUNENA_PAGE') . '</li>';

		if ($startpage > 1) {
			if ($endpage < $totalpages)
				$endpage --;
			$output .= '<li>' . CKunenaLink::GetThreadPageLink ( 'view', $catid, $threadid, 0, $limit, 1, '', $rel = 'follow' ) . '</li>';
			if ($startpage > 2) {
				$output .= '<li class="more">...</li>';
			}
		}

		for($i = $startpage; $i <= $endpage && $i <= $totalpages; $i ++) {
			if ($page == $i) {
				$output .= '<li class="active">' . $i . '</li>';
			} else {
				$output .= '<li>' . CKunenaLink::GetThreadPageLink ( 'view', $catid, $threadid, ($i-1)*$limit, $limit, $i, '', $rel = 'follow' ) . '</li>';
			}
		}

		if ($endpage < $totalpages) {
			if ($endpage < $totalpages - 1) {
				$output .= '<li class="more">...</li>';
			}

			$output .= '<li>' . CKunenaLink::GetThreadPageLink ( 'view', $catid, $threadid, ($totalpages-1)*$limit, $limit, $totalpages, '', $rel = 'follow' ) . '</li>';
		}

		$output .= '</ul>';
		return $output;
	}
}