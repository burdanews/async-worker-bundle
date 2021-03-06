<?php

namespace HBM\AsyncWorkerBundle\Service;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\Traits\ConsoleLoggerTrait;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class Informer {

  use ConsoleLoggerTrait;

  /**
   * @var array
   */
  private $config;

  /**
   * @var \Swift_Mailer
   */
  private $mailer;

  /**
   * @var Environment
   */
  private $twig;

  /**
   * Messenger constructor.
   *
   * @param array $config
   * @param LoggerInterface|NULL $logger
   */

  /**
   * Informer constructor.
   *
   * @param array $config
   * @param \Swift_Mailer $mailer
   * @param Environment $twig
   * @param ConsoleLogger $consoleLogger
   */
  public function __construct(array $config, \Swift_Mailer $mailer, Environment $twig, ConsoleLogger $consoleLogger) {
    $this->config = $config;
    $this->mailer = $mailer;
    $this->twig = $twig;
    $this->consoleLogger = $consoleLogger;
  }

  /**
   * Inform about job execution via email.
   *
   * @param AbstractJob $job
   * @param array $returnData
   *
   * @return bool
   */
  public function informAboutJob(AbstractJob $job, array $returnData) : bool {
    $email = $this->config['mail']['to'];
    if ($job->getEmail()) {
      $email = $job->getEmail();
    }

    // Check if email should be sent.
    if ($email && $this->mailer && $this->config['mail']['fromAddress'] && $job->getInform()) {
      $message = new \Swift_Message();
      $message->setTo($email);
      $message->setFrom($this->config['mail']['fromAddress'], $this->config['mail']['fromName']);

      // Render subject.
      $subject = $this->renderTemplateChain([
        $job->getTemplateFolder().'subject.text.twig',
        '@HBMAsyncWorker/Informer/subject.text.twig',
      ], $returnData);
      $message->setSubject($subject ?: $this->config['mail']['subject']);

      // Render text body.
      $bodyText = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.text.twig',
        '@HBMAsyncWorker/Informer/body.text.twig',
      ], $returnData);
      $message->setBody($bodyText, 'text/plain');

      // Render html body.
      $bodyHtml = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.html.twig',
        '@HBMAsyncWorker/Informer/body.html.twig',
      ], $returnData);

      // Fallback to nl2br of the text version.
      if (!$bodyHtml && $this->config['mail']['text2html']) {
        $bodyHtml = nl2br($bodyText);
      }
      if ($bodyHtml) {
        $message->addPart($bodyHtml, 'text/html');
      }

      $this->outputAndOrLog('Informing '.$email.' about job.', 'info');
      $this->mailer->send($message);

      return FALSE;
    }

    return FALSE;
  }

  /**
   * Render the first existing template.
   *
   * @param array $templates
   * @param array $data
   * @param string|NULL $default
   *
   * @return null|string
   */
  private function renderTemplateChain(array $templates, array $data, string $default = NULL) : ?string {
    foreach ($templates as $template) {
      try {
        if ($this->twig->getLoader()->exists($template)) {
          return $this->twig->render($template, $data);
        }
      } catch (\Throwable $e) {
      }
    }

    return $default;
  }

}
