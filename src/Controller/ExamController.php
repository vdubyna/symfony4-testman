<?php

namespace App\Controller;

use App\Entity\TestSession;
use App\Entity\TestSessionAnswer;
use App\Entity\TestSessionItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

class ExamController extends AbstractController
{
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
                     ->add('submit', SubmitType::class, ['label' => 'Next Question'])
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
            'testSession'  => $testSession,
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
            return $this->render('test_session/complete.html.twig', [
                'result' => $testSession->getResult(),
                'testSession' => $testSession,
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
            'testSession' => $testSession,
            'passed' => ($testSession->getCutoffSuccess() <= $result),
        ]);
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
