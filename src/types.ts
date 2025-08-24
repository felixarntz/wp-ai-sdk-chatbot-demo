/*
 * Types for the chatbot itself.
 */

export type ServerChatbotConfig = {
	messagesRoute: string;
	currentProviderMetadata: ProviderMetadata;
	currentModelMetadata: ModelMetadata;
};

export type ChatbotConfig = {
	labels: {
		title: string;
		subtitle: string;
		closeButton: string;
		sendButton: string;
		inputLabel: string;
		inputPlaceholder: string;
	};
	initialBotMessage?: string;
};

export type ChatbotMessage = Message & {
	type?: 'regular' | 'error';
};

/*
 * Types for the PHP AI Client SDK.
 * These were generated from the SDK's DTOs and enums.
 */

export enum FileType {
	Inline = 'inline',
	Remote = 'remote',
}

export type RemoteFile = {
	fileType: FileType.Remote;
	mimeType: string;
	url: string;
};

export type InlineFile = {
	fileType: FileType.Inline;
	mimeType: string;
	base64Data: string;
};

export type File = RemoteFile | InlineFile;

export enum MediaOrientation {
	Square = 'square',
	Landscape = 'landscape',
	Portrait = 'portrait',
}

export type FunctionCall = {
	id?: string;
	name?: string;
	args?: Record< string, unknown >;
};

export enum MessagePartChannel {
	Content = 'content',
	Thought = 'thought',
}

export enum MessagePartType {
	Text = 'text',
	File = 'file',
	FunctionCall = 'function_call',
	FunctionResponse = 'function_response',
}

export type FunctionResponse = {
	id?: string;
	name?: string;
	response: unknown;
};

export type TextMessagePart = {
	channel?: MessagePartChannel;
	type: MessagePartType.Text;
	text: string;
};

export type FileMessagePart = {
	channel?: MessagePartChannel;
	type: MessagePartType.File;
	file: File;
};

export type FunctionCallMessagePart = {
	channel?: MessagePartChannel;
	type: MessagePartType.FunctionCall;
	functionCall: FunctionCall;
};

export type FunctionResponseMessagePart = {
	channel?: MessagePartChannel;
	type: MessagePartType.FunctionResponse;
	functionResponse: FunctionResponse;
};

export type MessagePart =
	| TextMessagePart
	| FileMessagePart
	| FunctionCallMessagePart
	| FunctionResponseMessagePart;

export enum Modality {
	Text = 'text',
	Document = 'document',
	Image = 'image',
	Audio = 'audio',
	Video = 'video',
}

export enum OperationState {
	Starting = 'starting',
	Processing = 'processing',
	Succeeded = 'succeeded',
	Failed = 'failed',
	Canceled = 'canceled',
}

export enum ProviderType {
	Cloud = 'cloud',
	Server = 'server',
	Client = 'client',
}

export type ProviderMetadata = {
	id: string;
	name: string;
	type: ProviderType;
};

export enum Capability {
	TextGeneration = 'text_generation',
	ImageGeneration = 'image_generation',
	TextToSpeechConversion = 'text_to_speech_conversion',
	SpeechGeneration = 'speech_generation',
	MusicGeneration = 'music_generation',
	VideoGeneration = 'video_generation',
	EmbeddingGeneration = 'embedding_generation',
	ChatHistory = 'chat_history',
}

export type RequiredOption = {
	name: string;
	value:
		| string
		| number
		| boolean
		| null
		| unknown[]
		| Record< string, unknown >;
};

export type ModelRequirements = {
	requiredCapabilities: Capability[];
	requiredOptions: RequiredOption[];
};

export type SupportedOption = {
	name: string;
	supportedValues?: unknown[];
};

export type ModelMetadata = {
	id: string;
	name: string;
	description: string;
	capabilities: Capability[];
	supportedOptions: SupportedOption[];
	requirements: ModelRequirements;
};

export type ProviderModelsMetadata = {
	provider: ProviderMetadata;
	models: ModelMetadata[];
};

export enum Option {
	InputModalities = 'input_modalities',
	OutputModalities = 'output_modalities',
	SystemInstruction = 'system_instruction',
	CandidateCount = 'candidate_count',
	MaxTokens = 'max_tokens',
	Temperature = 'temperature',
	TopK = 'top_k',
	TopP = 'top_p',
	OutputMimeType = 'output_mime_type',
	OutputSchema = 'output_schema',
}

export enum HttpMethod {
	Get = 'GET',
	Post = 'POST',
	Put = 'PUT',
	Patch = 'PATCH',
	Delete = 'DELETE',
	Head = 'HEAD',
	Options = 'OPTIONS',
	Connect = 'CONNECT',
	Trace = 'TRACE',
}

export type Request = {
	method: HttpMethod;
	uri: string;
	headers: Record< string, string[] >;
	body?: string;
};

export type Response = {
	statusCode: number;
	headers: Record< string, string[] >;
	body?: string;
};

export type WebSearch = {
	allowedDomains?: string[];
	disallowedDomains?: string[];
};

export type FunctionDeclaration = {
	name: string;
	description: string;
	parameters?: unknown;
};

export type ModelConfig = {
	outputModalities?: Modality[];
	systemInstruction?: string;
	candidateCount?: number;
	maxTokens?: number;
	temperature?: number;
	topP?: number;
	topK?: number;
	stopSequences?: string[];
	presencePenalty?: number;
	frequencyPenalty?: number;
	logprobs?: boolean;
	topLogprobs?: number;
	functionDeclarations?: FunctionDeclaration[];
	webSearch?: WebSearch;
	outputFileType?: FileType;
	outputMimeType?: string;
	outputSchema?: Record< string, unknown >;
	outputMediaOrientation?: MediaOrientation;
	outputMediaAspectRatio?: string;
	outputSpeechVoice?: string;
	customOptions?: Record< string, unknown >;
};

export type ApiKeyRequestAuthentication = {
	apiKey: string;
};

export enum FinishReason {
	Stop = 'stop',
	Length = 'length',
	ContentFilter = 'content_filter',
	ToolCalls = 'tool_calls',
	Error = 'error',
}

export type Candidate = {
	message: Message;
	finishReason: FinishReason;
};

export type TokenUsage = {
	inputTokens: number;
	outputTokens: number;
	totalTokens: number;
};

export type GenerativeAiResult = {
	id: string;
	candidates: Candidate[];
	tokenUsage: TokenUsage;
	providerMetadata?: Record< string, unknown >;
};

export type SucceededGenerativeAiOperation = {
	id: string;
	state: OperationState.Succeeded;
	result: GenerativeAiResult;
};

export type OtherGenerativeAiOperation = {
	id: string;
	state:
		| OperationState.Starting
		| OperationState.Processing
		| OperationState.Failed
		| OperationState.Canceled;
};

export type GenerativeAiOperation =
	| SucceededGenerativeAiOperation
	| OtherGenerativeAiOperation;

export enum MessageRole {
	User = 'user',
	Model = 'model',
	System = 'system',
}

export type UserMessage = {
	role: MessageRole.User;
	parts: MessagePart[];
};

export type ModelMessage = {
	role: MessageRole.Model;
	parts: MessagePart[];
};

export type SystemMessage = {
	role: MessageRole.System;
	parts: MessagePart[];
};

export type Message = UserMessage | ModelMessage | SystemMessage;
