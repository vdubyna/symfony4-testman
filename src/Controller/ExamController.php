<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\TestSession;
use App\Entity\TestSessionItem;
use App\Entity\TestSessionTemplate;
use App\Entity\TestSessionTemplateItem;
use App\Form\TestSessionQuestionFormType;
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
     * @Route(path = "/admin/exam/generate", name = "exam_generate")
     */
    public function generateAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $testSessionTemplateRepository = $this->getDoctrine()->getRepository(TestSessionTemplate::class);
        /** @var TestSessionTemplate $testSessionTemplate */
        $testSessionTemplate = $testSessionTemplateRepository->find($request->get('id'));

        if ($request->isMethod('POST') && $request->get('email')) {
            // Generate Test session
            $testSession = new TestSession();
            $testSession->setEmail($request->get('email'));
            $testSession->setUuid(uuid_create(UUID_TYPE_RANDOM));
            $testSession->setTimeLimit($testSessionTemplate->getTimeLimit());
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

                $jsonAnswers = $serializer->serialize(
                    $question->getAnswers()->toArray(),
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
                'id' => $testSession->getId()
            ));

        }

        return new Response(
            "<html><body>
Genearate Test session and start testing: {$testSessionTemplate->getName()} <br />
<form action='/index.php/admin/exam/generate?id={$testSessionTemplate->getId()}' method='POST'>
EMAIL: <input type='text' name='email' /><br />
<button type='submit'>Submit</button>
</form>
</body></html>"
        );
    }

    /**
     * @Route(path = "/admin/exam/start", name = "exam_start")
     */
    public function startAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->find($request->get('id'));
        $questions = $testSession->getTestSessionItems();
        $questionsString = '';
        foreach ($questions as $question) {
            $questionsString .= $question->getQuestion() . '<br />';
        }

        return new Response(
            "<html><body>
Start Test Session ID: {$testSession->getId()} {$testSession->getTestSessionTemplate()->getName()}<br />
list of questions: <br />
{$questionsString}
<br />
Time limit: {$testSession->getTimeLimit()} minutes
<br />
Rules and Description
 <br />
<a href='/index.php/admin/exam/answer/0/{$testSession->getUuid()}'>Go to first Question</a>
<br />

</body></html>"
        );
    }

    /**
     * @Route(path = "/admin/exam/answer/{itemId}/{testSessionHash}", name = "exam_answer")
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

        $answers = $serializer->deserialize($answersJson, 'App\Entity\Answer[]', 'json');

        $answersString = '';
        foreach ($answers as $answer) {
            $answersString .= $answer->getAnswer() . '<br />';
        }

        $nextQuestion = $testSessionItem->getPosition()+1;
        $buttonText = 'Go to next Question';
        $url = "/index.php/admin/exam/answer/{$nextQuestion}/{$testSession->getUuid()}";

        if ($nextQuestion === $testSession->getQuestionsCount()) {
            $buttonText = 'Review Answers and submit Test Session';
            $url = "/index.php/admin/exam/review/{$testSession->getUuid()}";
        }


        $data = ['answers' => ['choices' => [1 => true]]];
        $form = $this->createFormBuilder($data)
            ->add('answers', ChoiceType::class, [
                'choices'  => [
                    'Maybe' => false,
                    'Yes' => true,
                    'No' => false,
                ],
                'expanded' => true
            ])
            ->add('submit', SubmitType::class)
            ->getForm();

        //dd($form);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //$data = $form->getData();
            //$testSessionItem->setResult(json_encode([1,2,3]));
            //$em->persist($testSessionItem);
            //$em->flush();
        }


        return $this->render('test_session_answer.html.twig', ['questionForm' => $form->createView()]);


//
//        return new Response(
//            "<html><body>
//Time limit: {$testSession->getTimeLimit()} <br />
//Question: {$testSessionItem->getQuestion()} <br />
//Answers: <br />
//{$answersString}
//<br />
//<br />
//<a href='{$url}'>{$buttonText}</a>
//<br />
//
//</body></html>"
//        );
    }

    /**
     * @Route(path = "/admin/exam/review/{testSessionHash}", name = "exam_review")
     */
    public function reviewAction(Request $request, $testSessionHash)
    {
        $testSessionItemRepository = $this->getDoctrine()->getRepository(TestSessionItem::class);
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        /** @var TestSessionItem[] $testSessionItems */
        $testSessionItems = $testSessionItemRepository->findBy([
            'testSession' => $testSession
        ]);


        $serializer = new Serializer(
            [new GetSetMethodNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );



        return new Response(
            "<html><body>

Please revew the responses and click complete if it is correct or review questions before complete

<a href='/index.php/admin/exam/complete/{$testSession->getUuid()}'>Complete</a>

</body></html>"
        );
    }

    /**
     * @Route(path = "/admin/exam/complete/{testSessionHash}", name = "exam_complete")
     */
    public function completeAction(Request $request, $testSessionHash)
    {
        $testSessionItemRepository = $this->getDoctrine()->getRepository(TestSessionItem::class);
        $testSessionRepository = $this->getDoctrine()->getRepository(TestSession::class);
        /** @var TestSession $testSession */
        $testSession = $testSessionRepository->findOneBy(['uuid' => $testSessionHash]);

        /** @var TestSessionItem[] $testSessionItems */
        $testSessionItems = $testSessionItemRepository->findBy([
            'testSession' => $testSession
        ]);

        return new Response(
            "<html><body>
Test results: 

Success: 
Fail: 
Thank you
</body></html>"
        );
    }
}
