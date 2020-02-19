<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\TestSession;
use App\Entity\TestSessionTemplate;
use App\Entity\TestSessionTemplateItem;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
            $dateTimeNow = new DateTime('now');
            $testSession->setExecuteAt($dateTimeNow);
            $testSession->setTestSessionTemplate($testSessionTemplate);

            // Generate questions based on template
            // Load ts items
            $testSessionTemplateItemRepository = $this->getDoctrine()->getRepository(TestSessionTemplateItem::class);
            $testSessionTemplateItems = $testSessionTemplateItemRepository->findBy([
                'test_session_template_id' => $testSessionTemplate->getId()
            ]);
            $testSessionQuestions = [];
            foreach ($testSessionTemplateItems as $testSessionTemplateItem) {
                /** @var TestSessionTemplateItem $testSessionTemplateItem */
                $questionRepository = $this->getDoctrine()->getRepository(Question::class);
                $questions = array_rand($questionRepository->findBy([
                    'category' => $testSessionTemplateItem->getCategory(),
                    'level' => $testSessionTemplateItem->getLevel()
                ]), $testSessionTemplateItem->getCutoff());

                array_push($testSessionQuestions, ...$questions);
            }

            foreach ($testSessionQuestions as $testSessionQuestion) {
                /** @var Question $testSessionQuestion */
                $testSessionItem = new TestSessionItem();
            }

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

        return new Response(
            "<html><body>
Start Test Session ID: {$testSession->getId()}
list of questions:
Time limit
Rules and Description
 
Go to first Question

</body></html>"
        );
    }
}
