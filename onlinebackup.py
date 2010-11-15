
import datetime
import time
from Crypto.Cipher import AES
import base64
import os
import shutil
import zipfile
import string
import sys
from ftplib import FTP
TMP='c:/tmp/'
# not tested
def encryptDirectory ( args, dirname, filenames ):
   print 'Directory',dirname
   encryptdir = encryptTargetDir+dirname.replace(top_level_dir,'')+'/'
   os.makedirs(encryptdir)
   for fileName in filenames:
      #print "Writing... " + dirname+'/'+fileName
      infile =dirname+'/'+fileName
      outfile = encryptdir+fileName+'.encrypt'
      if os.path.isfile(infile):
         print "Encrypting: %s"%outfile
         fo = open(outfile, 'wb')
         fi = open(infile, 'rb')
        
         while 1:
            #print fi.tell
            indata = fi.read(256)
            if not indata:
               break  
            encoded = EncodeAES(cipher, indata )
            #print len(encoded)
            packetlength = "%s"%len(encoded)
            fo.write(packetlength+':'+encoded)
            #print packetlength+':'+encoded
            #os.exit()

         fo.close
         fi.close
         #myZipFile.write( dirname+'/'+fileName )
         print '     File',fileName        
def zipDirectory ( args, dirname, filenames ):
   print 'Directory',dirname
   for fileName in filenames:
      print "   Zipping ... " + fileName
      args.write( dirname+'/'+fileName ) 

def encryptFile(zipfile):
   print "Encrypting: %s"%zipfile
   fi = open(zipfile, 'rb')
   fo = open(zipfile+".encrypt", 'wb')
   while 1:
      indata = fi.read(65536)
      if not indata:
         break
      remainder = divmod(len(indata), 16)
      if (remainder[1]==0):
         encoded = EncodeAES(cipher, indata )
         #decoded = DecodeAES(cipher, encoded )
      else:
         encoded = EncodeAESlast(cipher, indata )
         #decoded = DecodeAESlast(cipher, encoded )
      #if (indata != decoded):
         #print 'failed packet'
         #os.exit()
      packetlength = "%s"%len(encoded)
      print '.',
      fo.write(encoded)
   fo.close
   fi.close
def decryptFile(zipfile):
   print "Decrypting: %s%s"%(zipfile,".encrypt")
   fi = open(zipfile+".encrypt", 'rb')
   fo = open(zipfile.replace('.encript',''), 'wb')
   while 1:
      indata = fi.read(65536)
      if not indata:
         break  
      print '.',
      if(len(indata)==65536):
         decoded = DecodeAES(cipher, indata )
      else:
         decoded = DecodeAESlast(cipher, indata )
      fo.write(decoded)
   fo.close
   fi.close

# the block size for the cipher object; must be 16, 24, or 32 for AES
BLOCK_SIZE = 32

# the character used for padding--with a block cipher such as AES, the value
# you encrypt must be a multiple of BLOCK_SIZE in length.  This character is
# used to ensure that your value is always a multiple of BLOCK_SIZE
PADDING = '{'

# one-liner to sufficiently pad the text to be encrypted
pad = lambda s: s + (BLOCK_SIZE - len(s) % BLOCK_SIZE) * PADDING

# one-liners to encrypt/encode and decrypt/decode a string
# encrypt with AES, encode with base64
EncodeAESlast = lambda c, s: base64.b64encode(c.encrypt(pad(s)))
DecodeAESlast = lambda c, e: c.decrypt(base64.b64decode(e)).rstrip(PADDING)
EncodeAES = lambda c, s: c.encrypt(s)
DecodeAES = lambda c, e: c.decrypt(e)

#EncodeAES = lambda c, s: c.encrypt(pad(s))
#DecodeAES = lambda c, e: c.decrypt(e).rstrip(PADDING)

# generate a random secret key
#secret = os.urandom(BLOCK_SIZE)
#print secret
#os.exit()
secret = 'gdzMQy3GhLMSMGaAqSasdfasfdasdfasfdasfafXbN'
# create a cipher object using the random secret
cipher = AES.new(secret)

# encode a file
today = datetime.date.today()
strtoday = str(today)
encryptTargetDir = 'c:/tmp/'+strtoday+'/'
outfiledebug = "c:/tmp/%s_backup.aes_decrypt.zip"%strtoday

def shipFile(zipfile):
   # connect to host, default port
   ftp = FTP('acme','backup','backup')
   ftp.retrlines('LIST')     # list directory contents
   ftp.cwd('/vostro')
   print "directory set to "+ftp.pwd()
   print "Uploading...",
   f = open(zipfile+".encrypt", "rb")
   ### Hangs Here
   ftp.storbinary('STOR ' + zipfile.replace(TMP,'')+".encrypt", f)
   f.close()
   print "File uploaded"
   ftp.quit()


def zipEncryptShip(top_level_dir,short_name):
   zipoutfile = TMP+str(int(time.mktime(datetime.datetime.now().timetuple())))+short_name+'.zip'
   myZipFile = zipfile.ZipFile( zipoutfile, "w" )
   os.path.walk(top_level_dir, zipDirectory, myZipFile )
   myZipFile.close()
   encryptFile(zipoutfile)
   #decryptFile(zipoutfile)
   shipFile(zipoutfile)
   os.remove(zipoutfile)
   os.remove(zipoutfile+'.encrypt')


zipEncryptShip(sys.argv[1],sys.argv[2])
#for AT command
#C:\Python26\python.exe ftpBackup.py  c:/cygwin/home/marc/ruby ruby > backup.ruby.log
#run in c:\users\marc\python\utils
#zipEncryptShip("c:/cygwin/home/marc/ruby","ruby")
#zipEncryptShip("c:/xampp/htdocs/cake_personal","cake_personal")
#zipEncryptShip("c:/xampp/htdocs/cake_octagon","cake_octagon")
#zipEncryptShip("c:/xampp/htdocs/cake_rrg","cake_rrg")
#zipEncryptShip("c:/xampp/htdocs/familiescan_old","familiescan_V1")
#zipEncryptShip("c:/Users/marc/Pictures","pictures")
#zipEncryptShip("c:/Users/marc/python","python")
