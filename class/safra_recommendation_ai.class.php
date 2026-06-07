<?php
/**
 * OpenAI-backed recommendation generator for fertilization and liming.
 */

class SafraRecommendationAi
{
	/** @var DoliDB */
	private $db;

	/** @var string */
	public $error = '';

	/** @var array */
	public $errors = array();

	const DEFAULT_MODEL = 'gpt-5.4-mini';
	const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Generate an agronomic recommendation from a soil analysis.
	 *
	 * @param RecomendacaoAdubo $recommendation
	 * @param AnaliseSolo       $analysis
	 * @param array             $context
	 * @return array
	 */
	public function generate($recommendation, $analysis, array $context = array())
	{
		$apiKey = $this->getApiKey();
		if ($apiKey === '') {
			return $this->fail('SafraRecommendationAiMissingKey');
		}

		if (!function_exists('curl_init')) {
			return $this->fail('SafraRecommendationAiCurlMissing');
		}

		$model = $this->getModel();
		$payload = array(
			'model' => $model,
			'input' => array(
				array(
					'role' => 'developer',
					'content' => array(
						array('type' => 'input_text', 'text' => $this->buildInstructions()),
					),
				),
				array(
					'role' => 'user',
					'content' => array(
						array('type' => 'input_text', 'text' => $this->buildUserPrompt($recommendation, $analysis, $context)),
					),
				),
			),
			'max_output_tokens' => 1200,
		);

		$ch = curl_init(self::RESPONSES_ENDPOINT);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Authorization: Bearer '.$apiKey,
			),
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 2,
		));

		$response = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false) {
			return $this->fail('SafraRecommendationAiConnectionError: '.$curlError);
		}

		$decoded = json_decode($response, true);
		if (!is_array($decoded)) {
			return $this->fail('SafraRecommendationAiInvalidResponse');
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			$message = $this->extractApiError($decoded);
			return $this->fail($message !== '' ? $message : 'SafraRecommendationAiHttpError '.$httpCode);
		}

		$text = trim($this->extractOutputText($decoded));
		if ($text === '') {
			return $this->fail('SafraRecommendationAiEmptyResponse');
		}

		return array(
			'success' => true,
			'content' => $text,
			'model' => $model,
		);
	}

	/**
	 * @return string
	 */
	public function getModel()
	{
		$model = function_exists('getDolGlobalString') ? getDolGlobalString('SAFRA_OPENAI_MODEL', self::DEFAULT_MODEL) : self::DEFAULT_MODEL;
		$model = trim((string) $model);
		return $model !== '' ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * @return string
	 */
	private function getApiKey()
	{
		$key = function_exists('getDolGlobalString') ? getDolGlobalString('SAFRA_OPENAI_API_KEY', '') : '';
		return trim((string) $key);
	}

	/**
	 * @param string $message
	 * @return array
	 */
	private function fail($message)
	{
		$this->error = $message;
		$this->errors[] = $message;

		return array(
			'success' => false,
			'error' => $message,
		);
	}

	/**
	 * Instructions sent as the developer message.
	 *
	 * @return string
	 */
	private function buildInstructions()
	{
		return implode("\n", array(
			'Voce e um agronomo consultivo escrevendo para produtor rural no Brasil.',
			'Gere recomendacoes objetivas de adubacao e calagem usando somente os dados fornecidos.',
			'Nao invente dose, produto, produtividade ou diagnostico quando houver dados insuficientes.',
			'Quando faltar dado importante, use uma frase curta e acionavel, por exemplo: "Preencha a produtividade alvo para melhorar a recomendacao."',
			'Use linguagem pratica, sem juridiquês e sem paragrafo longo. Foque no que o produtor deve fazer no talhao.',
			'Use linguagem pratica, direta e sem paragrafo longo. Foque no que o produtor deve fazer no talhao.',
			'Responda em portugues do Brasil, com no maximo 250 palavras.',
			'Use Markdown simples: titulos com "##", bullets com "- " e negrito com "**" apenas para valores, riscos e acoes-chave.',
			'Nao use tabelas. Cada secao deve ter no maximo 2 bullets curtos.',
			'Nunca termine com convite, pergunta, oferta de nova interacao ou frase como "se quiser" ou "posso montar".',
			'Estrutura obrigatoria e unica:',
			'## Resumo pratico',
			'## Calagem',
			'## Adubacao',
			'## Manejo',
			'## Atencao',
			'## Isencao de responsabilidade',
			'Na secao "Isencao de responsabilidade", escreva somente 1 ou 2 linhas curtas informando que a recomendacao apoia a decisao e deve ser validada com agronomo responsavel e norma regional.',
		));
	}

	/**
	 * @param RecomendacaoAdubo $recommendation
	 * @param AnaliseSolo       $analysis
	 * @param array             $context
	 * @return string
	 */
	private function buildUserPrompt($recommendation, $analysis, array $context)
	{
		$lines = array(
			'Dados para recomendacao de adubacao e calagem:',
			'',
			'Recomendacao:',
			'- Referencia: '.$this->cleanValue($recommendation->ref),
			'- Rotulo: '.$this->cleanValue($recommendation->label),
			'- Cultura informada: '.$this->cleanValue($this->firstValue(array($recommendation->cultura, isset($context['culture']) ? $context['culture'] : null))),
			'- Produtividade alvo: '.$this->cleanValue($recommendation->produtividade_alvo).' sc/ha ou unidade informada pelo usuario',
			'- Area: '.$this->cleanValue($recommendation->area_ha).' ha',
			'- Projeto: '.$this->cleanValue(isset($context['project']) ? $context['project'] : null),
			'- Talhao: '.$this->cleanValue(isset($context['talhao']) ? $context['talhao'] : null),
			'',
			'Analise de solo:',
			'- Referencia: '.$this->cleanValue($analysis->ref),
			'- Data da coleta: '.$this->cleanValue($analysis->data_coleta),
			'- Localizacao: '.$this->cleanValue($analysis->localizacao),
			'- Latitude: '.$this->cleanValue($analysis->latitude),
			'- Longitude: '.$this->cleanValue($analysis->longitude),
			'- Profundidade da amostra: '.$this->cleanValue($analysis->profundidade_amostra),
			'- pH: '.$this->cleanValue($analysis->ph),
			'- Materia organica (%): '.$this->cleanValue($analysis->materia_organica),
			'- N total: '.$this->cleanValue($analysis->n_total),
			'- Fosforo disponivel: '.$this->cleanValue($analysis->fosforo),
			'- Potassio disponivel: '.$this->cleanValue($analysis->potassio),
			'- Calcio: '.$this->cleanValue($analysis->calcio),
			'- Magnesio: '.$this->cleanValue($analysis->magnesio),
			'- Enxofre: '.$this->cleanValue($analysis->enxofre),
			'- Textura: '.$this->cleanValue($analysis->textura),
			'- Densidade: '.$this->cleanValue($analysis->densidade),
			'- CTC: '.$this->cleanValue($analysis->ctc),
			'- Saturacao por bases V%: '.$this->cleanValue($analysis->saturacao_bases),
			'- Aluminio: '.$this->cleanValue($analysis->aluminio),
			'- Hidrogenio: '.$this->cleanValue($analysis->hidrogenio),
			'- Zinco: '.$this->cleanValue($analysis->zinco),
			'- Cobre: '.$this->cleanValue($analysis->cobre),
			'- Manganes: '.$this->cleanValue($analysis->manganes),
			'- Ferro: '.$this->cleanValue($analysis->ferro),
			'- Boro: '.$this->cleanValue($analysis->boro),
		);

		if (!empty($recommendation->description)) {
			$lines[] = '';
			$lines[] = 'Observacoes do usuario:';
			$lines[] = $this->cleanValue($recommendation->description);
		}

		return implode("\n", $lines);
	}

	/**
	 * @param array $values
	 * @return mixed
	 */
	private function firstValue(array $values)
	{
		foreach ($values as $value) {
			if ($value !== null && $value !== '') {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private function cleanValue($value)
	{
		if ($value === null || $value === '') {
			return 'nao informado';
		}

		if (is_float($value) || is_int($value)) {
			return (string) $value;
		}

		$value = strip_tags((string) $value);
		$value = preg_replace('/\s+/', ' ', $value);
		return trim($value) !== '' ? trim($value) : 'nao informado';
	}

	/**
	 * @param array $decoded
	 * @return string
	 */
	private function extractApiError(array $decoded)
	{
		if (!empty($decoded['error']['message'])) {
			return 'OpenAI: '.$decoded['error']['message'];
		}

		return '';
	}

	/**
	 * @param array $decoded
	 * @return string
	 */
	private function extractOutputText(array $decoded)
	{
		if (!empty($decoded['output_text']) && is_string($decoded['output_text'])) {
			return $decoded['output_text'];
		}

		$parts = array();
		if (!empty($decoded['output']) && is_array($decoded['output'])) {
			foreach ($decoded['output'] as $output) {
				if (empty($output['content']) || !is_array($output['content'])) {
					continue;
				}
				foreach ($output['content'] as $content) {
					if (!empty($content['text']) && is_string($content['text'])) {
						$parts[] = $content['text'];
					}
				}
			}
		}

		return implode("\n", $parts);
	}
}
