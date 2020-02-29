<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\TestSession;
use App\Entity\TestSessionAnswer;
use App\Entity\TestSessionItem;
use App\Entity\TestSessionTemplate;
use App\Entity\TestSessionTemplateItem;
use App\Form\TestSessionTemplateGenerateFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ExamController extends AbstractController
{
    /**
     * @Route(path = "/exam/generate", name = "exam_generate")
     */
    public function generateAction(Request $request, EntityManagerInterface $em)
    {
        $testSessionTemplateRepository = $em->getRepository(TestSessionTemplate::class);

        /** @var TestSessionTemplate $testSessionTemplate */
        $testSessionTemplate = $testSessionTemplateRepository->find($request->get('id'));

        $testSessionTemplateGenerateForm = $this->createForm(TestSessionTemplateGenerateFormType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('exam_generate', [
                'id' => $testSessionTemplate->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $testSessionTemplateGenerateForm->handleRequest($request);
        if ($testSessionTemplateGenerateForm->isSubmitted() && $testSessionTemplateGenerateForm->isValid()) {
            // Generate Test session
            $testSession = new TestSession();
            $testSession->setEmail($testSessionTemplateGenerateForm['email']->getData());
            $testSession->setUuid(uuid_create(UUID_TYPE_RANDOM));
            $testSession->setTimeLimit($testSessionTemplate->getTimeLimit());
            $testSession->setCutoffSuccess($testSessionTemplate->getCutoffSuccess());
            $testSession->setTestSessionTemplate($testSessionTemplate);
            $testSession->setTestSessionUrl($this->generateUrl('exam_before_start', [
                'testSessionHash' => $testSession->getUuid(),
            ]));

            // Generate questions based on template
            // Load ts items
            $questionRepository = $em->getRepository(Question::class);
            $testSessionTemplateItemRepository = $em->getRepository(TestSessionTemplateItem::class);
            $testSessionTemplateItems          = $testSessionTemplateItemRepository->findBy([
                'testSessionTemplate' => $testSessionTemplate,
            ]);
            $testSessionQuestions              = [];
            foreach ($testSessionTemplateItems as $testSessionTemplateItem) {
                /** @var TestSessionTemplateItem $testSessionTemplateItem */
                $questionsList      = $questionRepository->findBy([
                    'category' => $testSessionTemplateItem->getCategory(),
                    'level'    => $testSessionTemplateItem->getLevel(),
                ]);
                $questionIds        = (array)array_rand($questionsList, $testSessionTemplateItem->getCutoff());

                $questions = array_map(function ($id) use ($questionsList) {
                    return $questionsList[$id];
                }, $questionIds);

                array_push($testSessionQuestions, ...$questions);
            }


            $encoders    = [new JsonEncoder()];
            $normalizers = [new GetSetMethodNormalizer()];
            $serializer  = new Serializer($normalizers, $encoders);

            shuffle($testSessionQuestions);

            foreach ($testSessionQuestions as $position => $question) {
                /** @var Question $question */
                $testSessionItem = new TestSessionItem();

                $answers = $question->getAnswers()->toArray();
                shuffle($answers);
                $tesSessionAnswers = [];
                foreach ($answers as $key => $answer) {
                    /** @var Answer $answer */
                    $tesSessionAnswer = new TestSessionAnswer();
                    $tesSessionAnswer->setIsValid($answer->getIsValid());
                    $tesSessionAnswer->setAnswer($answer->getAnswer());
                    if ($question->getAnswerUidType() === 'num') {
                        $tesSessionAnswer->setId($key + 1);
                    } else {
                        $tesSessionAnswer->setId($this->numToAlpha($key));
                    }
                    $tesSessionAnswers[] = $tesSessionAnswer;
                }

                $jsonAnswers = $serializer->serialize(
                    $tesSessionAnswers,
                    'json',
                    [AbstractNormalizer::IGNORED_ATTRIBUTES => ['question']]
                );

                $testSessionItem->setTestSession($testSession)
                                ->setCategory($question->getCategory())
                                ->setLevel($question->getLevel())
                                ->setQuestion($question->getName())
                                ->setQuestionType($question->getQuestionType())
                                ->setPosition($position)
                                ->setAnswers($jsonAnswers);
                $em->persist($testSessionItem);
            }
            $testSession->setQuestionsCount(count($testSessionQuestions));

            $em->persist($testSession);
            $em->flush();

            return $this->redirectToRoute('exam_before_start', array(
                'testSessionHash' => $testSession->getUuid(),
            ));
        }

        return $this->render('test_session/generate.html.twig', [
            'testSessionTemplate'             => $testSessionTemplate,
            'testSessionTemplateGenerateForm' => $testSessionTemplateGenerateForm->createView(),
        ]);
    }

    /**
     * @Route(path = "/exam/before-start/{testSessionHash}", name = "exam_before_start")
     */
    public function beforeTestAction($testSessionHash, EntityManagerInterface $em)
    {
        $testSessionRepository = $em->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);
        if (!$testSession) {
            return $this->redirectToRoute('exam_404');
        }
        if ($testSession->getFinishedAt()) {
            $this->addFlash('success', 'Test result already submitted.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid()
            ]);
        }
        if ($testSession->getStartedAt()) {
            $this->addFlash('success', 'Test exam already started, go to first question');
            return $this->redirectToRoute('exam_answer', [
                'itemId'          => 0,
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        return $this->render('test_session/before_test.html.twig', [
            'testSession' => $testSession,
        ]);
    }

    /**
     * @Route(path = "/exam/404", name = "exam_404")
     */
    public function notFoundAction()
    {
        return $this->render('test_session/404.html.twig');
    }

    /**
     * @Route(path = "/exam/start/{testSessionHash}", name = "exam_start_test")
     */
    public function startTestAction(EntityManagerInterface $em, $testSessionHash)
    {
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        if (!$testSession) {
            return $this->redirectToRoute('exam_404');
        }
        if ($testSession->getFinishedAt()) {
            $this->addFlash('success', 'Test result already submitted.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid()
            ]);
        }
        if ($testSession->getStartedAt()) {
            $this->addFlash('success', 'Test exam already started, go to first question');
            return $this->redirectToRoute('exam_answer', [
                'itemId'          => 0,
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        $testSession->setStartedAt(new \DateTime());
        $em->persist($testSession);
        $em->flush();

        return $this->redirectToRoute('exam_answer', [
            'itemId'          => 0,
            'testSessionHash' => $testSession->getUuid(),
        ]);
    }

    /**
     * @Route(path = "/exam/answer/{itemId}/{testSessionHash}", name = "exam_answer")
     * @throws \Exception
     */
    public function answerAction(EntityManagerInterface $em, Request $request, $itemId, $testSessionHash)
    {
        $testSessionItemRepository = $em->getRepository(TestSessionItem::class);
        $testSessionRepository     = $em->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);
        /** @var TestSessionItem $testSessionItem */
        $testSessionItem = $testSessionItemRepository->findOneBy([
            'position'    => $itemId,
            'testSession' => $testSession,
        ]);

        if (!$testSession) {
            return $this->redirectToRoute('exam_404');
        }
        if ($testSession->getFinishedAt()) {
            $this->addFlash('success', 'Test result already submitted.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid()
            ]);
        }
        if (!$testSession->getStartedAt()) {
            $this->addFlash('success', 'Test exam not started, please start before answer the question');
            return $this->redirectToRoute('exam_before_start', [
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        $secondsToFinish = $this->getSecondsToFinishTestSession($testSession);
        if ($secondsToFinish <= 0) {
            $this->addFlash('success', 'Time is over, See the results below.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        $answers = $this->getDecodedTestSessionAnswers($testSessionItem);

        $answersChoices = [];
        foreach ($answers as $answer) {
            $answersChoices[$answer->getId()] = $answer->getId();
        }

        $data = json_decode($testSessionItem->getSubmittedAnswer(), true);

        $form = $this->createFormBuilder($data)
                     ->setAction($this->generateUrl('exam_answer', [
                         'itemId'          => $testSessionItem->getPosition(),
                         'testSessionHash' => $testSession->getUuid(),
                     ]))
                     ->add('answers', ChoiceType::class, [
                         'choices'      => $answersChoices,
                         'expanded'     => true,
                         'multiple'     => ($testSessionItem->getQuestionType() === 'checkboxes'),
                         'label'        => $testSessionItem->getQuestion(),
                         'choice_label' => function ($choice, $key, $value) use ($answers) {
                             /** @var TestSessionAnswer $currentAnswer */
                             $filteredAnswers = array_filter($answers, function ($answer) use ($choice, $key, $value) {
                                 /** @var TestSessionAnswer $answer */
                                 return ($key == $answer->getId()) ? true : false;
                                 /** @TODO with this */
                             });
                             $currentAnswer   = end($filteredAnswers);

                             return $currentAnswer->getAnswer();
                         },
                     ])
                     ->add('submit', SubmitType::class, ['label' => 'Next'])
                     ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $testSessionItem->setSubmittedAnswer(json_encode($data));
            $em->persist($testSessionItem);
            $em->flush();
            $nextQuestion = $testSessionItem->getPosition() + 1;
            if ($nextQuestion === $testSession->getQuestionsCount()) {
                return $this->redirectToRoute(
                    'exam_review', ['testSessionHash' => $testSession->getUuid()]);
            } else {
                return $this->redirectToRoute(
                    'exam_answer', [
                        'itemId'          => $nextQuestion,
                        'testSessionHash' => $testSession->getUuid(),
                    ]
                );
            }
        }

        return $this->render('test_session/answer.html.twig', [
            'questionForm'     => $form->createView(),
            'testSessionItem'  => $testSessionItem,
            'secondsToFinish' => $secondsToFinish,
            'completeUrl'      => $this->generateUrl('exam_complete', [
                'testSessionHash' => $testSession->getUuid(),
            ]),
        ]);
    }

    /**
     * @Route(path = "/exam/review/{testSessionHash}", name = "exam_review")
     * @throws \Exception
     */
    public function reviewAction(EntityManagerInterface $em, $testSessionHash)
    {
        $testSessionRepository = $em->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        if (!$testSession) {
            return $this->redirectToRoute('exam_404');
        }
        if ($testSession->getFinishedAt()) {
            $this->addFlash('success', 'Test result already submitted.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid()
            ]);
        }
        if (!$testSession->getStartedAt()) {
            $this->addFlash('success', 'Test exam not started, please start before answer the question');
            return $this->redirectToRoute('exam_before_start', [
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        $secondsToFinish = $this->getSecondsToFinishTestSession($testSession);
        if ($secondsToFinish <= 0) {
            $this->addFlash('success', 'Time is over, See the results below.');
            return $this->redirectToRoute('exam_complete', [
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }

        return $this->render('test_session/review.html.twig', [
            'secondsToFinish' => $secondsToFinish,
            'testSession' => $testSession,
            'completeUrl'      => $this->generateUrl('exam_complete', [
                'testSessionHash' => $testSession->getUuid(),
            ]),
        ]);
    }

    /**
     * @Route(path = "/exam/complete/{testSessionHash}", name = "exam_complete")
     */
    public function completeAction(EntityManagerInterface $em, $testSessionHash)
    {
        $testSessionRepository = $em->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        if (!$testSession) {
            return $this->redirectToRoute('exam_404');
        }
        if (!$testSession->getStartedAt()) {
            $this->addFlash('success', 'Test exam not started, please start before answer the question');
            return $this->redirectToRoute('exam_before_start', [
                'testSessionHash' => $testSession->getUuid(),
            ]);
        }
        if ($testSession->getFinishedAt()) {
            $this->addFlash('success', 'The results.');
            return $this->render('test_session/complete.html.twig', [
                'result' => $testSession->getResult(),
                'passed' => ($testSession->getCutoffSuccess() <= $testSession->getResult()),
            ]);
        }

        $totalCount     = $testSession->getQuestionsCount();
        $successCounter = 0;

        foreach ($testSession->getTestSessionItems() as $testSessionItem) {
            $submittedAnswers = json_decode($testSessionItem->getSubmittedAnswer(), true);
            $answers          = $this->getDecodedTestSessionAnswers($testSessionItem);

            if ($this->verifyAnswers($answers, $submittedAnswers)) {
                $testSessionItem->setResult(1);
                $successCounter++;
            } else {
                $testSessionItem->setResult(0);
            }
            $em->persist($testSessionItem);
        }
        $result = round(($successCounter / $totalCount) * 100, 2);
        $testSession->setResult($result);
        $testSession->setFinishedAt(new \DateTime());
        $em->persist($testSession);
        $em->flush();

        return $this->render('test_session/complete.html.twig', [
            'result' => $result,
            'passed' => ($testSession->getCutoffSuccess() <= $result),
        ]);
    }

    /**
     * @param $num
     *
     * @return string
     */
    private function numToAlpha($num)
    {
        return chr(substr("000" . ($num + 65), -3));
    }

    /**
     * @param $answers
     * @param $submittedAnswers
     *
     * @return bool
     */
    protected function verifyAnswers($answers, $submittedAnswers): bool
    {
        $allCheckboxesMarked = false;
        foreach ($answers as $answer) {
            /** @var TestSessionAnswer $answer */
            if ($answer->getIsValid()) {
                if ($submittedAnswers['answers'] == $answer->getId()) {
                    $allCheckboxesMarked = true;
                } else {
                    $allCheckboxesMarked = false;
                }
            }
            if (!$answer->getIsValid() && $submittedAnswers['answers'] == $answer->getId()) {
                $allCheckboxesMarked = false;
            }
        }

        return $allCheckboxesMarked;
    }

    /**
     * @param TestSessionItem $testSessionItem
     *
     * @return TestSessionAnswer[]
     */
    protected function getDecodedTestSessionAnswers(TestSessionItem $testSessionItem)
    {
        $answersJson = $testSessionItem->getAnswers();
        $serializer  = new Serializer(
            [new GetSetMethodNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );

        return $serializer->deserialize($answersJson, 'App\Entity\TestSessionAnswer[]', 'json');
    }

    /**
     * @param TestSession $testSession
     *
     * @return int
     * @throws \Exception
     */
    protected function getSecondsToFinishTestSession(TestSession $testSession): int
    {
        $currentTime = new \DateTime();
        /** @var \DateTime $startedTime */
        $startedTime     = $testSession->getStartedAt();
        $finishedTime    = $startedTime->add(new \DateInterval('PT' . $testSession->getTimeLimit() . 'M'));
        $secondsToFinish = $finishedTime->getTimestamp() - $currentTime->getTimestamp();

        return ($secondsToFinish > 0) ? $secondsToFinish : 0;
    }
}
