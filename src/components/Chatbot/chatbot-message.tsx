/**
 * External dependencies
 */
import Markdown from 'markdown-to-jsx';

/**
 * Internal dependencies
 */
import { useChatbotConfig } from '../../config';
import Loader from './loader';
import UserIcon from './user-icon';
import {
	ChatbotMessage as ChatbotMessageType,
	MessagePart,
	MessageRole,
	ResponseRendererProps,
} from '../../types';

const DefaultResponseRenderer = ( props: ResponseRendererProps ) => {
	const { text } = props;
	return (
		<Markdown options={ { forceBlock: true, forceWrapper: true } }>
			{ text }
		</Markdown>
	);
};

type ChatbotMessageProps = {
	/**
	 * The message content object.
	 */
	content: ChatbotMessageType;
	/**
	 * Whether the message is loading.
	 */
	loading?: boolean;
};

/**
 * Renders a chatbot message.
 *
 * @since 0.1.0
 *
 * @param props - Component props.
 * @returns The component to be rendered.
 */
export default function ChatbotMessage( props: ChatbotMessageProps ) {
	const { content, loading } = props;

	const ResponseRenderer =
		useChatbotConfig( 'ResponseRenderer' ) || DefaultResponseRenderer;

	const classSuffix =
		content.role === MessageRole.User ? 'user' : 'assistant';
	const errorClass =
		content.type === 'error' ? ' wp-ai-sdk-chatbot-demo-error' : '';

	return (
		<div
			className={ `wp-ai-sdk-chatbot-demo__message-container wp-ai-sdk-chatbot-demo__message-container--${ classSuffix }` }
		>
			<div
				className={ `wp-ai-sdk-chatbot-demo__avatar wp-ai-sdk-chatbot-demo__avatar--${ classSuffix }` }
			>
				<div
					className={ `wp-ai-sdk-chatbot-demo__avatar-container wp-ai-sdk-chatbot-demo__avatar-container--${ classSuffix }` }
				>
					{ classSuffix === 'assistant' && (
						<p
							className={ `wp-ai-sdk-chatbot-demo__avatar-letter wp-ai-sdk-chatbot-demo__avatar-letter--${ classSuffix }` }
						>
							B
						</p>
					) }
					{ classSuffix !== 'assistant' && (
						<UserIcon
							className={ `wp-ai-sdk-chatbot-demo__avatar-icon wp-ai-sdk-chatbot-demo__avatar-icon--${ classSuffix }` }
						/>
					) }
				</div>
			</div>
			{ loading && <Loader /> }
			{ ! loading && (
				<div
					className={ `wp-ai-sdk-chatbot-demo__message wp-ai-sdk-chatbot-demo__message--${ classSuffix }${ errorClass }` }
				>
					{ content.parts.map( ( part: MessagePart, index ) =>
						'text' in part && !! part.text ? (
							<ResponseRenderer
								key={ index }
								text={ part.text }
							/>
						) : null
					) }
					<div
						className={ `wp-ai-sdk-chatbot-demo__message-arrow wp-ai-sdk-chatbot-demo__message-arrow--${ classSuffix }${ errorClass }` }
					></div>
				</div>
			) }
		</div>
	);
}
