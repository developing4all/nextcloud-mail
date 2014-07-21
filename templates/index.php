<script id="mail-folder-template" type="text/x-handlebars-template">
	<h2 class="mail_account">{{email}}</h2>
	<ul class="mail_folders" data-account_id="{{id}}">
		{{#each folders}}
		<li data-folder_id="{{id}}"
		{{#if unseen}} class="unread"{{/if}}
		>
		<a>
			{{name}}
			{{#if unseen}}
			<span class="utils">{{unseen}}</span>
			{{/if}}
		</a>
		</li>
		{{/each}}
	</ul>
</script>
<script id="mail-messages-template" type="text/x-handlebars-template">
	{{#each this}}
	<div id="mail-message-summary-{{id}}" class="mail_message_summary {{#if flags.unseen}}unseen{{/if}}" data-message-id="{{id}}">
		<div class="sender-image">
			{{#if senderImage}}
			<img src="{{senderImage}}" width="32px" height="32px"/>
			{{else}}
			<div class="avatar" data-user="{{from}}" data-size="32"></div>
			{{/if}}
		</div>
		<div class="mail_message_summary_from">{{from}}</div>
		<div class="mail_message_summary_subject">{{subject}}</div>
		<div class="date">
				<span class="modified"
					  title="{{formatDate dateInt}}"
					  style="color:{{colorOfDate dateInt}}">{{relativeModifiedDate dateInt}}</span>
			<a class="icon-delete action delete"></a>
		</div>
		<div class="mail_message_loading icon-loading"></div>
		<div class="mail_message"></div>
	</div>
	{{/each}}
</script>
<script id="mail-message-template" type="text/x-handlebars-template">
	<div class="mail-message-attachments">
		{{#each attachments}}
		{{filename}} ( {{size}} )
		{{/each}}
	</div>
	<div class="mail-message-body">
		<div id="mail-content">
			{{{body}}}
		</div>
		<div class="reply-message-fields">
			<textarea name="body" class="reply-message-body"></textarea>
			<input class="reply-message-send" type="submit" value="<?php p($l->t('Reply')) ?>">
		</div>
	</div>
</script>

<div id="app">
	<div id="app-navigation" class="icon-loading"></div>
	<div id="app-content"  class="icon-loading">
		<form id="new-message">
			<input type="button" id="mail_new_message" value="<?php p($l->t('New Message')); ?>" style="display: none">

			<div id="new-message-fields" style="display: none">
				<input type="text" name="to" id="to"
					placeholder="<?php p($l->t('Recipient')); ?>" />
				<input type="text" name="subject" id="subject"
					placeholder="<?php p($l->t('Subject')); ?>" />
				<textarea name="body" id="new-message-body"
					placeholder="<?php p($l->t('Message')); ?> …"></textarea>
				<input id="new-message-send" class="send" type="submit"
					value="<?php p($l->t('Send')) ?>">
			</div>
		</form>

		<div id="mail_messages"></div>
	</div>
</div>
