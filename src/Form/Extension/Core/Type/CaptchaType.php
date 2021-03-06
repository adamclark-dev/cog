<?php

namespace Message\Cog\Form\Extension\Core\Type;

use Message\Cog\Http\Session;
use Message\Cog\Form\Extension\Core\EventListener\CaptchaEventListener;
use Message\Cog\Localisation\Translator;

use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CaptchaType extends Form\AbstractType
{
	const API_URL          = 'http://api.textcaptcha.com/';
	const SESSION_NAME     = 'captcha_answers';

	protected $_key;
	protected $_session;
	protected $_trans;

	protected $_fallback = [
		'question' => 'What colour is the sky?',
		'answer'   => 'blue',
	];

	public function __construct($apiKey, Session $session, Translator $trans)
	{
		$this->_key     = $apiKey;
		$this->_session = $session;
		$this->_trans   = $trans;
	}

	public function getName()
	{
		return 'captcha';
	}

	public function getParent()
	{
		return 'text';
	}

	public function buildView(Form\FormView $view, Form\FormInterface $form, array $options)
	{
		$view->vars['label'] = ($options['label'] ? $this->_trans->trans($options['label']) . ': ' : '') . $this->_getQuestion();
	}

	public function buildForm(Form\FormBuilderInterface $builder, array $options)
	{
		$builder->addEventSubscriber(new CaptchaEventListener($this->_session));
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver)
	{
		$resolver->setDefaults([
			'label'        => null,
			'required'     => true,
			'constraints'  => [
				new Constraints\NotBlank
			]
		]);
	}

	private function _getQuestion()
	{
		$question = $this->_getQuestionData();

		return $question['question'];
	}

	private function _getQuestionData()
	{
		$question = $this->_session->get(self::SESSION_NAME);

		if (!$question) {
			try {
				$xml = file_get_contents($this->_getXmlPath());

				$xml = new \SimpleXMLElement($xml);

				$question = [
					'question' => (string) $xml->question,
					'answer'   => $this->_convertAnswerToArray($xml),
				];
			}
			catch (\Exception $e) {
				$question = $this->_getFallbackQuestion();
			}
			$this->_session->set(self::SESSION_NAME, $question);
		}

		return $question;
	}

	private function _getXmlPath()
	{
		return self::API_URL . $this->_key;
	}

	private function _getFallbackQuestion()
	{
		return [
			'question' => $this->_fallback['question'],
			'answer'   => [
				md5($this->_fallback['answer']),
			]
		];
	}

	private function _convertAnswerToArray(\SimpleXMLElement $xml)
	{
		$answer = [];

		foreach ($xml->answer as $value) {
			$answer[] = (string) $value;
		}

		return $answer;
	}
}