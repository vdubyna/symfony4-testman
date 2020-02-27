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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $em = $this->getDoctrine()->getManager();
        $testSessionTemplateRepository = $this->getDoctrine()->getRepository(TestSessionTemplate::class);

        /** @var TestSessionTemplate $testSessionTemplate */
        $testSessionTemplate = $testSessionTemplateRepository->find($request->get('id'));

        $testSessionTemplateGenerateForm = $this->createForm(TestSessionTemplateGenerateFormType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('exam_generate', [
                'id' => $testSessionTemplate->getId()
            ])
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

            // Generate questions based on template
            // Load ts items
            $testSessionTemplateItemRepository = $this->getDoctrine()->getRepository(TestSessionTemplateItem::class);
            $testSessionTemplateItems = $testSessionTemplateItemRepository->findBy([
                'testSessionTemplate' => $testSessionTemplate
            ]);
            $testSessionQuestions = [];
            foreach ($testSessionTemplateItems as $testSessionTemplateItem) {
                /** @var TestSessionTemplateItem $testSessionTemplateItem */
                $questionRepository = $this->getDoctrine()->getRepository(Question::class);
                $questionsList = $questionRepository->findBy([
                    'category' => $testSessionTemplateItem->getCategory(),
                    'level' => $testSessionTemplateItem->getLevel()
                ]);
                $questionIds = (array) array_rand($questionsList, $testSessionTemplateItem->getCutoff());

                $questions = array_map(function($id) use ($questionsList) {
                    return $questionsList[$id];
                }, $questionIds);

                array_push($testSessionQuestions, ...$questions);
            }


            $encoders = [new JsonEncoder()];
            $normalizers = [new GetSetMethodNormalizer()];
            $serializer = new Serializer($normalizers, $encoders);

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
                        $tesSessionAnswer->setId($key+1);
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

            return $this->redirectToRoute('exam_start', array(
                'testSessionHash' => $testSession->getUuid()
            ));

        }

        return $this->render('test_session/generate.html.twig', [
            'testSessionTemplate' => $testSessionTemplate,
            'testSessionTemplateGenerateForm' => $testSessionTemplateGenerateForm->createView()
        ]);
    }

    /**
     * @Route(path = "/exam/start/{testSessionHash}", name = "exam_start")
     */
    public function startAction(Request $request, $testSessionHash)
    {
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        return $this->render('test_session/start.html.twig', [
            'testSession' => $testSession
        ]);
    }

    /**
     * @Route(path = "/exam/answer/{itemId}/{testSessionHash}", name = "exam_answer")
     */
    public function answerAction(EntityManagerInterface $em, Request $request, $itemId, $testSessionHash)
    {
        // TODO if post method - save the answer.
        // TODO If results already recorded it should be shown on the form

        $testSessionItemRepository = $this->getDoctrine()->getRepository(TestSessionItem::class);
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        /** @var TestSessionItem $testSessionItem */
        $testSessionItem = $testSessionItemRepository->findOneBy([
            'position' => $itemId,
            'testSession' => $testSession
        ]);

        $answersJson = $testSessionItem->getAnswers();
        $serializer = new Serializer(
            [new GetSetMethodNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
        $answers = $serializer->deserialize($answersJson, 'App\Entity\TestSessionAnswer[]', 'json');

        $answersChoices = [];
        foreach ($answers as $answer) {
            $answersChoices[$answer->getId()] = $answer->getId();
        }

        $data = json_decode($testSessionItem->getSubmittedAnswer(), true);

        $form = $this->createFormBuilder($data)
            ->setAction($this->generateUrl('exam_answer', [
                'itemId' => $testSessionItem->getPosition(),
                'testSessionHash' => $testSession->getUuid()
            ]))
            ->add('answers', ChoiceType::class, [
                'choices'  => $answersChoices,
                'expanded' => true,
                'label' => $testSessionItem->getQuestion(),
                'choice_label' => function ($choice, $key, $value) use ($answers) {

                    /** @TODO with this */
                    /** @var TestSessionAnswer $currentAnswer */
                    $filteredAnswers = array_filter($answers, function($answer) use ($choice, $key, $value) {
                        /** @var TestSessionAnswer $answer */
                        return ($key == $answer->getId()) ? true : false;
                    });

                    $currentAnswer = end($filteredAnswers);
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
            $nextQuestion = $testSessionItem->getPosition()+1;
            if ($nextQuestion === $testSession->getQuestionsCount()) {
                return $this->redirectToRoute(
                    'exam_review', ['testSessionHash' => $testSession->getUuid()]);
            } else {
                return $this->redirectToRoute(
                    'exam_answer', [
                        'itemId' => $nextQuestion,
                        'testSessionHash' => $testSession->getUuid()
                    ]
                );
            }
        }

        return $this->render('test_session/answer.html.twig', [
            'questionForm' => $form->createView()
        ]);
    }

    /**
     * @Route(path = "/exam/review/{testSessionHash}", name = "exam_review")
     */
    public function reviewAction(Request $request, $testSessionHash)
    {
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        return $this->render('test_session/review.html.twig', [
            'testSession' => $testSession,
        ]);
    }

    /**
     * @Route(path = "/exam/complete/{testSessionHash}", name = "exam_complete")
     */
    public function completeAction(Request $request, $testSessionHash)
    {
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        $totalCount = $testSession->getQuestionsCount();
        $successCounter = 0;
        $em = $this->getDoctrine()->getManager();
        foreach ($testSession->getTestSessionItems() as $testSessionItem) {
            $submittedAnswers = json_decode($testSessionItem->getSubmittedAnswer(), true);
            $answersJson = $testSessionItem->getAnswers();
            $serializer = new Serializer(
                [new GetSetMethodNormalizer(), new ArrayDenormalizer()],
                [new JsonEncoder()]
            );
            $answers = $serializer->deserialize($answersJson, 'App\Entity\TestSessionAnswer[]', 'json');

            foreach ($answers as $answer) {
                /** @var TestSessionAnswer $answer */
                if ($answer->getIsValid() && $submittedAnswers['answers'] == $answer->getId()) {
                    $testSessionItem->setResult(1);
                    $successCounter++;
                } else {
                    $testSessionItem->setResult(0);
                }
                $em->persist($testSessionItem);
            }
        }
        $em->flush();
        $result = round(($successCounter/$totalCount)*100, 2);

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
}
