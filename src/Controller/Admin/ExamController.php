<?php

namespace App\Controller\Admin;

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
     * @Route(path = "/admin/exam/generate", name = "admin_exam_generate")
     */
    public function generateAction(Request $request, EntityManagerInterface $em)
    {
        $testSessionTemplateRepository = $em->getRepository(TestSessionTemplate::class);

        /** @var TestSessionTemplate $testSessionTemplate */
        $testSessionTemplate = $testSessionTemplateRepository->find($request->get('id'));

        $testSessionTemplateGenerateForm = $this->createForm(TestSessionTemplateGenerateFormType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('admin_exam_generate', [
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

            return $this->redirectToRoute('easyadmin', array(
                'action' => 'show',
                'entity' => 'TestSession',
                'id' => $testSession->getId()
            ));

        }

        return $this->render('test_session/generate.html.twig', [
            'testSessionTemplate'             => $testSessionTemplate,
            'form' => $testSessionTemplateGenerateForm->createView(),
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
