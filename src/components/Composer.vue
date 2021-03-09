<template>
<div width="100%" style="height:100%; background-color: #5C5A5A">
	<div v-if="state === STATES.EDITING" class="message-composer" style="max-width: 900px; margin-left: auto; margin-right: auto; width: 75%; background-color: #5C5A5A;">
		<div class="composer-fields mail-account">
			<label class="from-label" for="from" style="color:white;">
				{{ t('mail', 'From') }}
			</label>
			<Multiselect
				id="from"
				v-model="selectedAlias"
				:options="aliases"
				label="name"
				track-by="selectId"
				:searchable="false"
				:hide-selected="true"
				:custom-label="formatAliases"
				:placeholder="t('mail', 'Select account')"
				:clear-on-select="false"
				@keyup="onInputChanged" />
		</div>
		<div class="composer-fields">
			<label class="to-label" for="to" style="color:white;">
				{{ t('mail', 'To') }}
			</label>
			<Multiselect
				id="to"
				ref="toLabel"
				v-model="selectTo"
				:options="selectableRecipients"
				:taggable="true"
				label="label"
				track-by="email"
				:multiple="true"
				:placeholder="t('mail', 'Contact or email address …')"
				:clear-on-select="false"
				:show-no-options="false"
				:preserve-search="true"
				style="background-color: inherit; color: white"
				@keyup="onInputChanged"
				@tag="onNewToAddr"
				@search-change="onAutocomplete" />
			<a v-if="!showCC"
				class="copy-toggle"
				href="#"
				style="color: white;"
				@click.prevent="showCC = true">
				{{ t('mail', '+ Cc/Bcc') }}
			</a>
		</div>
		<div v-if="showCC" class="composer-fields">
			<label for="cc" class="cc-label" style="color:white;">
				{{ t('mail', 'Cc') }}
			</label>
			<Multiselect
				id="cc"
				v-model="selectCc"
				:options="selectableRecipients"
				:taggable="true"
				label="label"
				track-by="email"
				:multiple="true"
				:placeholder="t('mail', '')"
				:clear-on-select="false"
				:show-no-options="false"
				:preserve-search="true"
				@keyup="onInputChanged"
				@tag="onNewCcAddr"
				@search-change="onAutocomplete">
				<span slot="noOptions">{{ t('mail', 'No contacts found.') }}</span>
			</Multiselect>
		</div>
		<div v-if="showCC" class="composer-fields">
			<label for="bcc" class="bcc-label" style="color:white;">
				{{ t('mail', 'Bcc') }}
			</label>
			<Multiselect
				id="bcc"
				v-model="selectBcc"
				:options="selectableRecipients"
				:taggable="true"
				label="label"
				track-by="email"
				:multiple="true"
				:placeholder="t('mail', '')"
				:show-no-options="false"
				:preserve-search="true"
				@keyup="onInputChanged"
				@tag="onNewBccAddr"
				@search-change="onAutocomplete">
				<span slot="noOptions">{{ t('mail', 'No contacts found.') }}</span>
			</Multiselect>
		</div>
		<div class="composer-fields">
			<label for="subject" class="subject-label hidden-visually" style="color:white;">
				{{ t('mail', 'Subject') }}
			</label>
			<input
				id="subject"
				v-model="subjectVal"
				type="text"
				name="subject"
				class="subject"
				autocomplete="off"
				:placeholder="t('mail', 'Subject …')"
				style="background: inherit; color: white;"
				@keyup="onInputChanged"
				>
		</div>
		<div v-if="noReply" class="warning noreply-warning">
			{{ t('mail', 'This message came from a noreply address so your reply will probably not be read.') }}
		</div>
		<div v-if="mailvelope.keysMissing.length" class="warning noreply-warning">
			{{
				t('mail', 'The following recipients do not have a PGP key: {recipients}.', {
					recipients: mailvelope.keysMissing.join(', '),
				})
			}}
		</div>
		<div class="composer-fields">
				<Editor api-key="no-api-key" tinymce-script-src='/ntapps/mail/public/tinymce/tinymce.min.js'
					:init="{
						height: '600px',
					    branding: false,
					    content_style: '',
					    setup: function(editor) {
					        editor.ui.registry.addMenuItem('templateinsert', {
					            text: 'Insert template...',
					            onAction: function() {
									getTemplates(editor)
					            }
					        });
							editor.ui.registry.addMenuItem('ebrochureinsert', {
					            text: 'Insert ebrochure...',
					            onAction: function() {
									getEbrochures(editor)
					            }
					        });
					    },
					    menu: {
					        templatetools: {
					            title: 'Templates',
					            items: 'templateinsert ebrochureinsert'
					        }
					    },
					    plugins: 'table lists advlist anchor autolink charmap code print pagebreak image imagetools visualblocks fullscreen paste wordcount',
					    toolbar: 'mysidebar undo redo | styleselect | bold italic | image | alignleft aligncenter alignright alignjustify | outdent indent',
					    menubar: 'file edit view insert format table templatetools',
					    table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol'
					}"
					key="editor-rich"
					v-model="bodyVal"
					:html="true"
					name="body"
					class="message-body"
					style="width:100%"
					:placeholder="t('mail', 'Write message …')"
					:focus="isReply"
					:bus="bus"
					@input="onInputChanged" />
			</div>
		<div class="composer-actions" style="background: white;">
			<ComposerAttachments v-model="attachments" :bus="bus" @upload="onAttachmentsUploading" />
			<div class="composer-actions-right">
				<p class="composer-actions-draft">
					<span v-if="!canSaveDraft" id="draft-status">{{ t('mail', 'Can not save draft because this account does not have a drafts mailbox configured.') }}</span>
					<span v-else-if="savingDraft === true" id="draft-status">{{ t('mail', 'Saving draft …') }}</span>
					<span v-else-if="savingDraft === false" id="draft-status">{{ t('mail', 'Draft saved') }}</span>
				</p>
				<Actions>
					<ActionButton icon="icon-upload" @click="onAddLocalAttachment">
						{{
							t('mail', 'Upload attachment')
						}}
					</ActionButton>
					<ActionButton icon="icon-folder" @click="onAddCloudAttachment">
						{{
							t('mail', 'Add attachment from Files')
						}}
					</ActionButton>
					<ActionButton :disabled="encrypt" icon="icon-folder" @click="onAddCloudAttachmentLink">
						{{
							t('mail', 'Add attachment link from Files')
						}}
					</ActionButton>
					<ActionCheckbox
						:checked="!encrypt && !editorPlainText"
						:disabled="encrypt"
						@check="editorMode = 'html'"
						@uncheck="editorMode = 'plaintext'">
						{{ t('mail', 'Enable formatting') }}
					</ActionCheckbox>
				</Actions>
				<div>
					<input
						class="submit-message send primary icon-confirm-white"
						type="submit"
						:value="submitButtonTitle"
						:disabled="!canSend"
						@click="onSend">
				</div>
			</div>
		</div>
	</div>
	<Loading v-else-if="state === STATES.UPLOADING" :hint="t('mail', 'Uploading attachments …')" />
	<Loading v-else-if="state === STATES.SENDING" :hint="t('mail', 'Sending …')" />
	<div v-else-if="state === STATES.ERROR" class="emptycontent">
		<h2 style="color:white;">{{ t('mail', 'Error sending your message') }}</h2>
		<p v-if="errorText" style="color:white;">
			{{ errorText }}
		</p>
		<button class="button" @click="state = STATES.EDITING">
			{{ t('mail', 'Go back') }}
		</button>
		<button class="button primary" @click="onSend">
			{{ t('mail', 'Retry') }}
		</button>
	</div>
	<div v-else class="emptycontent">
		<h2 style="color:white;">{{ t('mail', 'Message sent!') }}</h2>
		<button v-if="!isReply" class="button primary" @click="reset">
			{{ t('mail', 'Write another message') }}
		</button>
	</div>
	<div class="loading" id="pageLoader">
		<div class="loader"></div>
	</div>
</div>
</template>

<script>
import debounce from 'lodash/fp/debounce'
import uniqBy from 'lodash/fp/uniqBy'
import Autosize from 'vue-autosize'
import debouncePromise from 'debounce-promise'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { translate as t } from '@nextcloud/l10n'
import Vue from 'vue'

import ComposerAttachments from './ComposerAttachments'
import { findRecipient } from '../service/AutocompleteService'
import { detect, html, plain, toHtml, toPlain } from '../util/text'
import Loading from './Loading'
import logger from '../logger'
import TextEditor from './TextEditor'
import Editor from '@tinymce/tinymce-vue';
import { buildReplyBody } from '../ReplyBuilder'
import MailvelopeEditor from './MailvelopeEditor'
import { getMailvelope } from '../crypto/mailvelope'
import { isPgpgMessage } from '../crypto/pgp'
import { matchError } from '../errors/match'
import NoSentMailboxConfiguredError
	from '../errors/NoSentMailboxConfiguredError'
import NoDraftsMailboxConfiguredError
	from '../errors/NoDraftsMailboxConfiguredError'

const debouncedSearch = debouncePromise(findRecipient, 500)

Vue.use(Autosize)

const STATES = Object.seal({
	EDITING: 0,
	UPLOADING: 1,
	SENDING: 2,
	ERROR: 3,
	FINISHED: 4,
})

export default {
	name: 'Composer',
	components: {
		MailvelopeEditor,
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionLink,
		ComposerAttachments,
		Loading,
		Multiselect,
		TextEditor,
		Editor
	},
	props: {
		fromAccount: {
			type: Number,
			default: () => undefined,
		},
		to: {
			type: Array,
			default: () => [],
		},
		cc: {
			type: Array,
			default: () => [],
		},
		bcc: {
			type: Array,
			default: () => [],
		},
		subject: {
			type: String,
			default: '',
		},
		body: {
			type: Object,
			default: () => html(''),
		},
		draft: {
			type: Function,
			required: true,
		},
		send: {
			type: Function,
			required: true,
		},
		replyTo: {
			type: Object,
			required: false,
			default: () => undefined,
		},
		forwardFrom: {
			type: Object,
			required: false,
			default: () => undefined,
		},
	},
	data() {
		return {
			showCC: this.cc.length > 0,
			selectedAlias: -1, // Fixed in `beforeMount`
			autocompleteRecipients: this.to.concat(this.cc).concat(this.bcc),
			newRecipients: [],
			subjectVal: this.subject,
			bodyVal: toHtml(this.body).value,
			attachments: [],
			noReply: this.to.some((to) => to.email.startsWith('noreply@') || to.email.startsWith('no-reply@')),
			draftsPromise: Promise.resolve(),
			attachmentsPromise: Promise.resolve(),
			canSaveDraft: true,
			savingDraft: undefined,
			saveDraftDebounced: debounce(700, this.saveDraft),
			state: STATES.EDITING,
			errorText: undefined,
			STATES,
			selectTo: this.to,
			selectCc: this.cc,
			selectBcc: this.bcc,
			bus: new Vue(),
			encrypt: false,
			mailvelope: {
				available: false,
				keyRing: undefined,
				keysMissing: [],
			},
			editorMode: 'html',
		}
	},
	computed: {
		aliases() {
			let cnt = 0
			const accounts = this.$store.getters.accounts.filter((a) => !a.isUnified)
			const aliases = accounts.flatMap((account) => [
				{
					id: account.id,
					aliasId: null,
					selectId: cnt++,
					editorMode: account.editorMode,
					signature: account.signature,
					name: account.name,
					emailAddress: account.emailAddress,
				},
				account.aliases.map((alias) => {
					return {
						id: account.id,
						aliasId: alias.id,
						selectId: cnt++,
						editorMode: account.editorMode,
						signature: account.signature,
						name: alias.name,
						emailAddress: alias.alias,
					}
				}),
			])
			return aliases.flat()
		},
		allRecipients() {
			return this.selectTo.concat(this.selectCc).concat(this.selectBcc)
		},
		selectableRecipients() {
			return this.newRecipients
				.concat(this.autocompleteRecipients)
				.map((recipient) => ({ ...recipient, label: recipient.label || recipient.email }))
		},
		isForward() {
			return this.forwardFrom !== undefined
		},
		isReply() {
			return this.replyTo !== undefined
		},
		canSend() {
			if (this.encrypt && this.mailvelope.keysMissing.length) {
				return false
			}

			return this.selectTo.length > 0 || this.selectCc.length > 0 || this.selectBcc.length > 0
		},
		editorPlainText() {
			return this.editorMode === 'plaintext'
		},
		submitButtonTitle() {
			if (!this.mailvelope.available) {
				return t('mail', 'Send')
			}

			return this.encrypt ? t('mail', 'Encrypt and send') : t('mail', 'Send unencrypted')
		},

	},
	watch: {
		'$route.params.threadId'() {
			this.reset()
		},
		allRecipients() {
			this.checkRecipientsKeys()
		},
	},
	async beforeMount() {
		this.setAlias()
		this.initBody()

		await this.onMailvelopeLoaded(await getMailvelope())
	},
	mounted() {
		this.$refs.toLabel.$el.focus()
		// event is triggered when user clicks 'new message' in navigation
		this.$root.$on('newMessage', () => {
			this.draftsPromise
				.then(() => {
					return this.saveDraft(this.getMessageData)
				})
				.then(() => {
					// wait for the draft to be saved before resetting the message content
					this.reset()
				})
		})
		// let tmScript = document.createElement('script')
		// tmScript.setAttribute('src', '/custom_apps/mail/js/tinymce/tinymce.min.js')
		// tmScript.setAttribute('type', 'text/javascript')
		// tmScript.setAttribute('nonce', '')
		// tmScript.setAttribute('defer', '')
    	// document.head.appendChild(tmScript)
		setTimeout(function () {
			$('#pageLoader')[0].style.display = "none";
		}, 3000);
	},
	beforeDestroy() {
		this.$root.$off('newMessage')

		window.removeEventListener('mailvelope', this.onMailvelopeLoaded)
	},
	methods: {
		setAlias() {
			const previous = this.selectedAlias
			if (this.fromAccount) {
				this.selectedAlias = this.aliases.find((alias) => alias.id === this.fromAccount)
			} else {
				this.selectedAlias = this.aliases[0]
			}
			if (previous === undefined) {
				this.editorMode = this.selectedAlias.editorMode
			}
		},
		async checkRecipientsKeys() {
			if (!this.encrypt || !this.mailvelope.available) {
				return
			}

			const recipients = this.allRecipients.map((r) => r.email)
			const keysValid = await this.mailvelope.keyRing.validKeyForAddress(recipients)
			logger.debug('recipients keys validated', { recipients, keysValid })
			this.mailvelope.keysMissing = recipients.filter((r) => keysValid[r] === false)
		},
		initBody() {
			if (this.replyTo) {
				this.bodyVal = this.bodyWithSignature(
					this.selectedAlias,
					buildReplyBody(
						this.editorPlainText ? toPlain(this.body) : toHtml(this.body),
						this.replyTo.from[0],
						this.replyTo.dateInt
					).value
				).value
			} else if (this.forwardFrom) {
				this.bodyVal = this.bodyWithSignature(
					this.selectedAlias,
					buildReplyBody(
						this.editorPlainText ? toPlain(this.body) : toHtml(this.body),
						this.forwardFrom.from[0],
						this.forwardFrom.dateInt
					).value
				).value
			} else {
				this.bodyVal = this.bodyWithSignature(this.selectedAlias, this.bodyVal).value
			}
		},
		selectTemplate(id) {
			console.log("selected " + id);
		},
		recipientToRfc822(recipient) {
			if (recipient.email === recipient.label) {
				// From mailto or sender without proper label
				return recipient.email
			} else if (recipient.label === '') {
				// Invalid label
				return recipient.email
			} else if (recipient.email.search(/^[a-zA-Z]+:/) === 0) {
				// Group integration
				return recipient.email
			} else {
				// Proper layout with label
				return `"${recipient.label}" <${recipient.email}>`
			}
		},
		getMessageData(id) {
			return {
				account: this.selectedAlias.id,
				aliasId: this.selectedAlias.aliasId,
				to: this.selectTo.map(this.recipientToRfc822).join(', '),
				cc: this.selectCc.map(this.recipientToRfc822).join(', '),
				bcc: this.selectBcc.map(this.recipientToRfc822).join(', '),
				draftId: id,
				subject: this.subjectVal,
				body: this.encrypt ? plain(this.bodyVal) : html(this.bodyVal),
				attachments: this.attachments,
				messageId: this.replyTo ? this.replyTo.databaseId : undefined,
				isHtml: !this.editorPlainText,
			}
		},
		saveDraft(data) {
			this.savingDraft = true
			this.draftsPromise = this.draftsPromise
				.then((id) => {
					const draftData = data(id)
					if (
						!id
						&& !draftData.subject
						&& !draftData.body
						&& !draftData.cc
						&& !draftData.bcc
						&& !draftData.to
					) {
						// this might happen after a call to reset()
						// where the text input gets reset as well
						// and fires an input event
						logger.debug('Nothing substantial to save, ignoring draft save')
						this.savingDraft = false
						return id
					}
					return this.draft(draftData)
				})
				.then((uid) => {
					// It works (again)
					this.canSaveDraft = true

					return uid
				})
				.catch(async(error) => {
					console.error('could not save draft', error)
					const canSave = await matchError(error, {
						[NoDraftsMailboxConfiguredError.getName()]() {
							return false
						},
						default() {
							return true
						},
					})
					if (!canSave) {
						this.canSaveDraft = false
					}
				})
				.then((uid) => {
					this.savingDraft = false
					return uid
				})
			return this.draftsPromise
		},
		onInputChanged() {
			this.saveDraftDebounced(this.getMessageData)
		},
		onAddLocalAttachment() {
			this.bus.$emit('onAddLocalAttachment')
		},
		onAddCloudAttachment() {
			this.bus.$emit('onAddCloudAttachment')
		},
		onAddCloudAttachmentLink() {
			this.bus.$emit('onAddCloudAttachmentLink')
		},
		onAutocomplete(term) {
			if (term === undefined || term === '') {
				return
			}
			debouncedSearch(term).then((results) => {
				this.autocompleteRecipients = uniqBy('email')(this.autocompleteRecipients.concat(results))
			})
		},
		onAttachmentsUploading(uploaded) {
			this.attachmentsPromise = this.attachmentsPromise
				.then(() => uploaded)
				.catch((error) => logger.error('could not upload attachments', { error }))
				.then(() => logger.debug('attachments uploaded'))
		},
		async onMailvelopeLoaded(mailvelope) {
			this.encrypt = isPgpgMessage(this.body)
			this.mailvelope.available = true
			logger.info('Mailvelope loaded', {
				encrypt: this.encrypt,
				isPgpgMessage: isPgpgMessage(this.body),
				keyRing: this.mailvelope.keyRing,
			})
			this.mailvelope.keyRing = await mailvelope.getKeyring()
			await this.checkRecipientsKeys()
		},
		onNewToAddr(addr) {
			this.onNewAddr(addr, this.selectTo)
		},
		onNewCcAddr(addr) {
			this.onNewAddr(addr, this.selectCc)
		},
		onNewBccAddr(addr) {
			this.onNewAddr(addr, this.selectBcc)
		},
		onNewAddr(addr, list) {
			const res = {
				label: addr, // TODO: parse if possible
				email: addr, // TODO: parse if possible
			}
			this.newRecipients.push(res)
			list.push(res)
		},
		async onSend() {
			if (this.encrypt) {
				logger.debug('get encrypted message from mailvelope')
				await this.$refs.mailvelopeEditor.pull()
			}

			this.state = STATES.UPLOADING

			return this.attachmentsPromise
				.then(() => (this.state = STATES.SENDING))
				.then(() => this.draftsPromise)
				.then(this.getMessageData)
				.then((data) => this.send(data))
				.then(() => logger.info('message sent'))
				.then(() => (this.state = STATES.FINISHED))
				.catch(async(error) => {
					logger.error('could not send message', { error })
					this.errorText = await matchError(error, {
						[NoSentMailboxConfiguredError.getName()]() {
							return t('mail', 'No sent mailbox configured. Please pick one in the account settings.')
						},
						default(error) {
							if (error && error.toString) {
								return error.toString()
							}
						},
					})
					this.state = STATES.ERROR
				})
		},
		showAddEbrochure() {
			alert('Not Implemented');
		},
		showAddTemplate() {
			alert('Not Implemented')
		},
		reset() {
			this.draftsPromise = Promise.resolve() // "resets" draft uid as well
			this.selectTo = []
			this.selectCc = []
			this.selectBcc = []
			this.subjectVal = ''
			this.bodyVal = ''
			this.attachments = []
			this.errorText = undefined
			this.state = STATES.EDITING
			this.autocompleteRecipients = []
			this.newRecipients = []

			this.setAlias()
			this.initBody()
			Vue.nextTick(() => {
				// toLabel may not be on the DOM yet
				// (because "Message sent" is shown)
				// so we defer the focus call
				this.$refs.toLabel.$el.focus()
			})
		},
		/**
		 * Format aliases for the Multiselect
		 * @param {Object} alias the alias to format
		 * @returns {string}
		 */
		formatAliases(alias) {
			if (!alias.name) {
				return alias.emailAddress
			}

			return `${alias.name} <${alias.emailAddress}>`
		},
		bodyWithSignature(alias, body) {
			if (!alias || !alias.signature) {
				return html(body)
			}

			return html(body)
				.append(html('<br>--<br>'))
				.append(toHtml(detect(alias.signature)))
		},

		getTemplates(editor) {
			$('#pageLoader')[0].style.display = "block";
			$.ajax({
				url: '/apps/mail/ajax/template/all/email',
				type: 'GET',
				dataType: 'JSON',
				success: function (data) {
					console.log(data);
					let templates = [];
					for(let i = 0; i < data.length; i++) {
						templates[i] = {
							type: 'htmlpanel',
							html: `<div class='templateButton' data-id="${data[i].id}">
								<img class='templateThumbnail' src='${data[i].thumbnail}'>
								<div class='templateData'>
									<p>${data[i].name}</p>
								</div>
							</div>`
						};
					}
					const templatesDialogue = {
						title: 'Templates',
						body: {
							type: 'tabpanel',
							tabs: [
								{
									name: 'Email',
									title: 'email',
									items: [
										{
											type: 'grid',
											columns: 3,
											items: templates
										}
									]
								}
							]
						},
						buttons: [
							{
								type: 'custom',
								name: 'add-template',
								text: 'Add Template',
								disabled: true
							}
						],
						initialData: {},
						onTabChange: function(dialogApi, details) {
							dialogApi.showTab(details.oldTabName);
						},
						onAction: function(dialogApi, details) {
							$('#pageLoader')[0].style.display = "block";
							let id = $('.templateButton.active')[0].getAttribute('data-id');
							dialogApi.close();
							$.ajax({
								url: '/apps/mail/ajax/template/render/' + id + "/email/1FM5K8D8XKGA27551/621",
								type: 'GET',
								dataType: 'JSON',
								success: function (data) {
									editor.execCommand(
										'mceInsertContent',
										false,
										data.data
									);
									$('#pageLoader')[0].style.display = "none";
								},
								error: function (e) {
									console.log(e);
								}
							});
							
						}
					};
					$('#pageLoader')[0].style.display = "none";
					let dialogApi = editor.windowManager.open(templatesDialogue);
					$(document).on("click", ".templateButton" , function() {
						$('.templateButton').removeClass('active');
						$(this).addClass('active');
						dialogApi.enable('add-template');
					});

				},
				error: function (e) {
					console.log(e);
				}
			});
		},

		getEbrochures(editor) {
			$('#pageLoader')[0].style.display = "block";
			$.ajax({
				url: '/apps/mail/ajax/template/all/ebrochure',
				type: 'GET',
				dataType: 'JSON',
				success: function (data) {
					console.log(data);
					let templates = [];
					for(let i = 0; i < data.length; i++) {
						templates[i] = {
							type: 'htmlpanel',
							html: `<div class='templateButton' data-id="${data[i].id}">
								<img class='templateThumbnail' src='${data[i].thumbnail}'>
								<div class='templateData'>
									<p>${data[i].name}</p>
								</div>
							</div>`
						};
					}
					const templatesDialogue = {
						title: 'EBrochures',
						body: {
							type: 'tabpanel',
							tabs: [
								{
									name: 'Email',
									title: 'email',
									items: [
										{
											type: 'grid',
											columns: 3,
											items: templates
										}
									]
								}
							]
						},
						buttons: [
							{
								type: 'custom',
								name: 'add-template',
								text: 'Add Template',
								disabled: true
							}
						],
						initialData: {},
						onTabChange: function(dialogApi, details) {
							dialogApi.showTab(details.oldTabName);
						},
						onAction: function(dialogApi, details) {
							$('#pageLoader')[0].style.display = "block";
							let id = $('.templateButton.active')[0].getAttribute('data-id');
							dialogApi.close();
							$.ajax({
								url: '/apps/mail/ajax/template/render/' + id + "/ebrochure/1FM5K8D8XKGA27551/621",
								type: 'GET',
								dataType: 'JSON',
								success: function (data) {
									editor.execCommand(
										'mceInsertContent',
										false,
										data.data
									);
									$('#pageLoader')[0].style.display = "none";
								},
								error: function (e) {
									console.log(e);
								}
							});
							
						}
					};
					$('#pageLoader')[0].style.display = "none";
					let dialogApi = editor.windowManager.open(templatesDialogue);
					$(document).on("click", ".templateButton" , function() {
						$('.templateButton').removeClass('active');
						$(this).addClass('active');
						dialogApi.enable('add-template');
					});

				},
				error: function (e) {
					console.log(e);
				}
			});
		}
	},
}
</script>

<style>
.templateButton {
	margin: 10px !important; 
	width: 100px !important; 
	height: 150px !important; 
	border: 1px solid black !important; 
	overflow: hidden !important;
}

.templateButton.active {
	background-color: lightblue !important;
	border: 1px solid black !important; 
}
.templateButton:hover {
	border: 1px solid blue !important; 
	cursor: pointer;
}
.templateButton img {
	width: 100% !important; 
	height: auto !important;
}
.templateButton .templateData {
	padding: 5px !important;
	  -webkit-touch-callout: none; /* iOS Safari */
    -webkit-user-select: none; /* Safari */
     -khtml-user-select: none; /* Konqueror HTML */
       -moz-user-select: none; /* Old versions of Firefox */
        -ms-user-select: none; /* Internet Explorer/Edge */
            user-select: none; /* Non-prefixed version, currently
                                  supported by Chrome, Edge, Opera and Firefox */
}
.loading {
    position: fixed;
    top: 0; right: 0;
    bottom: 0; left: 0;
    background: rgba(0,0,0,0.6);
	z-index: 99;
}
/* .loader {
    left: 50%;
    margin-left: -4em;
    font-size: 10px;
    border: .8em solid rgba(218, 219, 223, 1);
    border-left: .8em solid rgba(58, 166, 165, 1);
    animation: spin 1.1s infinite linear;
} */
.loader, .loader:after {
    border-radius: 50%;
    width: 8em;
    height: 8em;
    display: block;
    position: absolute;
    top: 50%;
    margin-top: -4.05em;
}

.tox-dialog-wrap {
	z-index: 98 !important;
}

@keyframes spin {
  0% {
    transform: rotate(360deg);
  }
  100% {
    transform: rotate(0deg);
  }
}
</style>

<style lang="scss" scoped>
.message-composer {
	margin: 0;
	z-index: 100;
}

.composer-actions {
	display: flex;
	flex-direction: row;
	align-items: flex-end;
	justify-content: space-between;
	position: sticky;
	bottom: 0;
	padding: 12px;
	background: linear-gradient(rgba(255, 255, 255, 0), var(--color-main-background-translucent) 50%);
}

.composer-actions-right {
	display: flex;
	align-items: center;
}

.composer-fields {
	display: flex;
	align-items: center;
	border-top: 1px solid var(--color-border);

	&.mail-account {
		border-top: none;

		& > .multiselect {
			max-width: none;
			min-height: auto;
		}
	}

	.multiselect,
	input,
	TextEditor {
		flex-grow: 1;
		max-width: none;
		border: none;
		border-radius: 0;
	}

	.multiselect {
		margin-right: 12px;
	}
}

.subject {
	font-size: 20px;
	font-weight: bold;
	margin: 0;
	padding: 24px 12px;
	color: white;
}

::placeholder .subject {
	color: white;
}

.warning-box {
	padding: 5px 12px;
	border-radius: 0;
}

.message-body {
	min-height: 300px;
	width: 100%;
	margin: 0;
	padding: 12px;
	border: none !important;
	outline: none !important;
	box-shadow: none !important;
}

#draft-status {
	padding: 5px;
	opacity: 0.5;
	font-size: small;
}

.from-label,
.to-label,
.copy-toggle,
.cc-label,
.bcc-label {
	padding: 12px;
	cursor: text;
	color: var(--color-text-maxcontrast);
	width: 100px;
	text-align: right;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.h2 {
	color:white;
}

.p {
	color: white;
}

.bcc-label {
	top: initial;
	bottom: 0;
}

.copy-toggle {
	cursor: pointer;
	width: initial;

	&:hover,
	&:focus {
		color: var(--color-main-text);
	}
}

.reply {
	min-height: 100px;
}

.send {
	padding: 12px 18px 13px 36px;
	background-position: 12px center;
	margin-left: 4px;
}
</style>

<style>
.multiselect__tag {
	color: white !important;
}
</style>

<style lang="scss" scoped>
::v-deep .multiselect .multiselect__tags {
	border: none !important;
}
.submit-message.send.primary.icon-confirm-white {
color: var(--color-main-background);
}
</style>
