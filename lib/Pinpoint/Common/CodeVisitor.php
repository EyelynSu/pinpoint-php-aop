<?php declare(strict_types=1);
/**
 * Copyright 2020-present NAVER Corp.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * User: eeliu
 * Date: 2/13/19
 * Time: 10:33 AM
 */

namespace Pinpoint\Common;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\NodeTraverser;
//use Pinpoint\Common\GenRequiredBIFileHelper;

class CodeVisitor extends NodeVisitorAbstract
{

    protected $ospIns;
    private $curNamespace;
    private $curName;
    public $originClassFile;
    public $proxiedClassFile;

    protected $builtInAr = []; // curl_init PDO

    public function __construct(GenOriginClassFileHelper $originClassFile,GenProxiedClassFileHelper $proxiedClassFile)
    {
        $this->originClassFile = $originClassFile;
        $this->proxiedClassFile = $proxiedClassFile;
    }

    public function enterNode(Node $node)
    {
        if($node instanceof Node\Stmt\Namespace_)
        {
            $this->curNamespace = $node->name->toString();
            /// set namespace
            $this->originClassFile->handleEnterNamespaceNode($node);
            $this->proxiedClassFile->handleEnterNamespaceNode($node);
        }
        elseif ($node instanceof Node\Stmt\Use_){
            $this->proxiedClassFile->handlerUseNode($node);
            $this->originClassFile->handlerUseNode($node);
        }
        elseif ($node instanceof Node\Stmt\Class_){
            if(empty($node->name->toString())){
                throw new \Exception("can't AOP an anonymous class");
            }
            $this->curName = empty($this->curNamespace) ? ($node->name->toString()):($this->curNamespace.'\\'.$node->name->toString());
            $this->originClassFile->handleEnterClassNode($node);
            $this->proxiedClassFile->handleEnterClassNode($node);
        }
        elseif( $node instanceof Node\Stmt\Trait_){
            if(empty($node->name->toString())){
                throw new \Exception("can't AOP an anonymous trait");
            }
            $this->curName = empty($this->curNamespace) ? ($node->name->toString()):($this->curNamespace.'\\'.$node->name->toString());

            $this->proxiedClassFile->handleEnterTraitNode($node);
            $this->originClassFile->handleEnterTraitNode($node);
        }elseif ($node instanceof Node\Stmt\ClassMethod)
        {
            $this->originClassFile->handleClassEnterMethodNode($node);
            $this->proxiedClassFile->handleClassEnterMethodNode($node);
        }
        elseif ( $node instanceof Node\Stmt\Return_)
        {
            $this->originClassFile->markHasReturn($node);
        }
        elseif ($node instanceof Node\Expr\Yield_){
            $this->originClassFile->markHasYield($node);
        }
        elseif ($node instanceof Node\Expr\ClassConstFetch){
            return $this->proxiedClassFile->handleEnterClassConstFetch($node);
        }elseif ($node instanceof  Node\Expr\New_){
            return $this->proxiedClassFile->handleEnterNew_($node);
        }elseif ($node instanceof Node\Expr\FuncCall){
            return $this->proxiedClassFile->handleEnterFuncCall($node);
        }
    }


    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod){
            $this->originClassFile->handleLeaveMethodNode($node);
            $this->proxiedClassFile->handleLeaveMethodNode($node);
        }elseif ($node instanceof Node\Name\FullyQualified){
        }
        elseif ($node instanceof Node\Scalar\MagicConst){
            return $this->proxiedClassFile->handleMagicConstNode($node);
        }elseif ($node instanceof Node\Stmt\Namespace_){
            return $this->proxiedClassFile->handleLeaveNamespace($node);
        }
        elseif ($node instanceof Node\Stmt\Class_) {
            $this->proxiedClassFile->handleLeaveClassNode($node);
        }elseif ($node instanceof Node\Stmt\Trait_){

            $this->proxiedClassFile->handleLeaveTraitNode($node);
        }
        elseif ($node instanceof Node\Stmt\UseUse){

            return $node;
        }
    }

    public function afterTraverse(array $nodes)
    {
        $node = $this->proxiedClassFile->handleAfterTravers($nodes);

        $this->proxiedClassFile->fileNodeDoneCB($node,$this->proxiedClassFile->myLoaderName);

        $node = $this->originClassFile->handleAfterTravers($nodes);

        $this->originClassFile->fileNodeDoneCB($node,$this->originClassFile->fileName);

    }

}
